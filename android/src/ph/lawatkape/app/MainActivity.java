package ph.lawatkape.app;

import android.app.Activity;
import android.app.AlertDialog;
import android.app.DownloadManager;
import android.content.ActivityNotFoundException;
import android.content.ContentValues;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.net.Uri;
import android.net.http.SslError;
import android.os.Build;
import android.os.Bundle;
import android.os.Environment;
import android.os.VibrationEffect;
import android.os.Vibrator;
import android.print.PrintAttributes;
import android.print.PrintDocumentAdapter;
import android.print.PrintManager;
import android.provider.MediaStore;
import android.text.InputType;
import android.view.WindowManager;
import android.webkit.CookieManager;
import android.webkit.JavascriptInterface;
import android.webkit.SslErrorHandler;
import android.webkit.URLUtil;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.EditText;
import android.widget.FrameLayout;
import android.widget.Toast;

import org.json.JSONArray;

/**
 * Lawa't Kape for Android: the shop system, full screen.
 *
 * The app holds no copy of the system. It loads the live one from the shop's
 * server over the shop Wi-Fi, so every update to the system shows up without
 * reinstalling. What it adds is what a browser tab can't do well on a phone:
 * no address bar, the screen kept awake at the register, the camera and
 * gallery for Barista AI photos, printing, downloads, vibration for order
 * reminders, and a clear screen when the phone is off the shop network.
 */
public class MainActivity extends Activity {

    private static final String PREFS = "lawatkape";
    private static final String KEY_SERVER = "server_url";
    // The app server's own address. The staff name lawatkape.lab goes through
    // the HTTPS proxy, whose certificate phones don't trust.
    private static final String DEFAULT_SERVER = "http://192.168.2.100";
    private static final String OFFLINE_PAGE = "file:///android_asset/offline.html";
    private static final int FILE_CHOOSER_REQUEST = 1;

    // Runs on every page: routes window.print() and navigator.vibrate() to the
    // phone, which a plain WebView would otherwise ignore.
    private static final String BRIDGE_JS =
            "(function(){if(window.__lawatKapeApp)return;window.__lawatKapeApp=true;"
            + "window.print=function(){LawatKapeApp.print();};"
            + "navigator.vibrate=function(p){try{LawatKapeApp.vibrate(JSON.stringify(p));}catch(e){}return true;};"
            + "})();";

    private WebView web;
    private ValueCallback<Uri[]> fileCallback;
    private Uri cameraUri;
    private boolean leavingOffline = false;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        // A register phone that dims mid-order is a nuisance; the screen stays
        // on while the app is in front.
        getWindow().addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON);

        web = new WebView(this);
        FrameLayout root = new FrameLayout(this);
        root.addView(web, new FrameLayout.LayoutParams(
                FrameLayout.LayoutParams.MATCH_PARENT, FrameLayout.LayoutParams.MATCH_PARENT));
        setContentView(root);

        configureWebView();

        if (savedInstanceState != null) {
            web.restoreState(savedInstanceState);
        } else {
            web.loadUrl(serverUrl() + "/");
        }
    }

    private void configureWebView() {
        WebSettings s = web.getSettings();
        s.setJavaScriptEnabled(true);
        s.setDomStorageEnabled(true);
        // Order-reminder chimes play without a tap first.
        s.setMediaPlaybackRequiresUserGesture(false);
        s.setLoadWithOverviewMode(true);
        s.setUseWideViewPort(true);
        s.setSupportZoom(false);
        s.setBuiltInZoomControls(false);
        s.setAllowFileAccess(false);
        s.setAllowContentAccess(true);
        s.setUserAgentString(s.getUserAgentString() + " LawatKapeApp/" + appVersion());

        CookieManager.getInstance().setAcceptCookie(true);

        web.addJavascriptInterface(new Bridge(), "LawatKapeApp");
        web.setWebViewClient(new ShopClient());
        web.setWebChromeClient(new ShopChromeClient());
        web.setDownloadListener((url, userAgent, contentDisposition, mimeType, length) ->
                download(url, userAgent, contentDisposition, mimeType));
    }

    // ---- Server address -------------------------------------------------

    private String serverUrl() {
        return getSharedPreferences(PREFS, MODE_PRIVATE).getString(KEY_SERVER, DEFAULT_SERVER);
    }

    private void saveServerUrl(String input) {
        String url = input.trim();
        if (url.isEmpty()) {
            url = DEFAULT_SERVER;
        }
        if (!url.startsWith("http://") && !url.startsWith("https://")) {
            url = "http://" + url;
        }
        while (url.endsWith("/")) {
            url = url.substring(0, url.length() - 1);
        }
        getSharedPreferences(PREFS, MODE_PRIVATE).edit().putString(KEY_SERVER, url).apply();
    }

    private String serverHost() {
        return Uri.parse(serverUrl()).getHost();
    }

    private void showServerDialog() {
        EditText input = new EditText(this);
        input.setInputType(InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_VARIATION_URI);
        input.setText(serverUrl());
        input.setSelectAllOnFocus(true);
        int pad = (int) (20 * getResources().getDisplayMetrics().density);
        FrameLayout box = new FrameLayout(this);
        box.setPadding(pad, pad / 2, pad, 0);
        box.addView(input);

        new AlertDialog.Builder(this)
                .setTitle("Server address")
                .setMessage("The shop system's address on the shop Wi-Fi. Normally " + DEFAULT_SERVER + ".")
                .setView(box)
                .setPositiveButton("Save", (d, w) -> {
                    saveServerUrl(input.getText().toString());
                    web.loadUrl(serverUrl() + "/");
                })
                .setNeutralButton("Reset", (d, w) -> {
                    saveServerUrl(DEFAULT_SERVER);
                    web.loadUrl(serverUrl() + "/");
                })
                .setNegativeButton("Cancel", null)
                .show();
    }

    // ---- Pages ----------------------------------------------------------

    private void showOffline() {
        leavingOffline = true;
        web.loadUrl(OFFLINE_PAGE);
    }

    private class ShopClient extends WebViewClient {
        @Override
        public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
            Uri uri = request.getUrl();
            String scheme = uri.getScheme() == null ? "" : uri.getScheme();
            String host = uri.getHost() == null ? "" : uri.getHost();

            if (scheme.equals("http") || scheme.equals("https")) {
                if (host.equals(serverHost()) || host.equals("wifi.lawatkape.lab")) {
                    return false;
                }
                // The staff name goes through the HTTPS proxy, which the phone
                // won't accept; the same page from the server works.
                if (host.equals("lawatkape.lab")) {
                    String path = uri.getEncodedPath() == null ? "/" : uri.getEncodedPath();
                    String query = uri.getEncodedQuery() == null ? "" : "?" + uri.getEncodedQuery();
                    view.loadUrl(serverUrl() + path + query);
                    return true;
                }
            }

            // Anything else (another website, a phone number, an email) opens
            // in the app that handles it.
            try {
                startActivity(new Intent(Intent.ACTION_VIEW, uri));
            } catch (ActivityNotFoundException e) {
                Toast.makeText(MainActivity.this, "No app on this phone can open that link.", Toast.LENGTH_SHORT).show();
            }
            return true;
        }

        @Override
        public void onPageFinished(WebView view, String url) {
            super.onPageFinished(view, url);
            view.evaluateJavascript(BRIDGE_JS, null);
            CookieManager.getInstance().flush();

            // Back from the offline screen: don't let Back return to it.
            if (leavingOffline && url != null && !url.startsWith(OFFLINE_PAGE)) {
                view.clearHistory();
                leavingOffline = false;
            }
        }

        @Override
        public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
            if (request.isForMainFrame()) {
                showOffline();
            }
        }

        @Override
        public void onReceivedSslError(WebView view, SslErrorHandler handler, SslError error) {
            // Never accept a certificate the phone doesn't trust.
            handler.cancel();
        }
    }

    // ---- Photos for Barista AI ------------------------------------------

    private class ShopChromeClient extends WebChromeClient {
        @Override
        public boolean onShowFileChooser(WebView view, ValueCallback<Uri[]> callback, FileChooserParams params) {
            if (fileCallback != null) {
                fileCallback.onReceiveValue(null);
            }
            fileCallback = callback;
            cameraUri = null;

            String accept = "*/*";
            if (params.getAcceptTypes() != null) {
                for (String type : params.getAcceptTypes()) {
                    if (type != null && !type.trim().isEmpty()) {
                        accept = type.trim();
                        break;
                    }
                }
            }

            Intent pick = new Intent(Intent.ACTION_GET_CONTENT);
            pick.addCategory(Intent.CATEGORY_OPENABLE);
            pick.setType(accept.contains(",") ? "*/*" : accept);
            Intent chooser = Intent.createChooser(pick, "Choose a photo");

            // Offer the camera too. It saves into the phone's Pictures, so no
            // file sharing setup is needed (Android 10 and newer).
            if (accept.startsWith("image") && Build.VERSION.SDK_INT >= 29) {
                Intent camera = new Intent(MediaStore.ACTION_IMAGE_CAPTURE);
                if (camera.resolveActivity(getPackageManager()) != null) {
                    ContentValues values = new ContentValues();
                    values.put(MediaStore.Images.Media.DISPLAY_NAME, "lawatkape_" + System.currentTimeMillis() + ".jpg");
                    values.put(MediaStore.Images.Media.MIME_TYPE, "image/jpeg");
                    values.put(MediaStore.Images.Media.RELATIVE_PATH, Environment.DIRECTORY_PICTURES + "/Lawat Kape");
                    cameraUri = getContentResolver().insert(MediaStore.Images.Media.EXTERNAL_CONTENT_URI, values);
                    if (cameraUri != null) {
                        camera.putExtra(MediaStore.EXTRA_OUTPUT, cameraUri);
                        chooser.putExtra(Intent.EXTRA_INITIAL_INTENTS, new Intent[]{camera});
                    }
                }
            }

            try {
                startActivityForResult(chooser, FILE_CHOOSER_REQUEST);
                return true;
            } catch (ActivityNotFoundException e) {
                fileCallback = null;
                callback.onReceiveValue(null);
                return false;
            }
        }
    }

    @Override
    protected void onActivityResult(int requestCode, int resultCode, Intent data) {
        if (requestCode != FILE_CHOOSER_REQUEST || fileCallback == null) {
            super.onActivityResult(requestCode, resultCode, data);
            return;
        }

        Uri[] result = null;
        boolean picked = data != null && (data.getData() != null || data.getClipData() != null);
        if (resultCode == RESULT_OK && picked) {
            result = WebChromeClient.FileChooserParams.parseResult(resultCode, data);
        } else if (resultCode == RESULT_OK && cameraUri != null) {
            result = new Uri[]{cameraUri};
        }

        // The camera's placeholder photo, when the camera wasn't what was used.
        if (cameraUri != null && (result == null || result[0] != cameraUri)) {
            try {
                getContentResolver().delete(cameraUri, null, null);
            } catch (Exception ignored) {
            }
        }

        fileCallback.onReceiveValue(result);
        fileCallback = null;
        cameraUri = null;
    }

    // ---- Downloads (sales export) -----------------------------------------

    private void download(String url, String userAgent, String contentDisposition, String mimeType) {
        try {
            String name = URLUtil.guessFileName(url, contentDisposition, mimeType);
            DownloadManager.Request request = new DownloadManager.Request(Uri.parse(url));
            String cookies = CookieManager.getInstance().getCookie(url);
            if (cookies != null) {
                request.addRequestHeader("Cookie", cookies);
            }
            request.addRequestHeader("User-Agent", userAgent);
            request.setMimeType(mimeType);
            request.setTitle(name);
            request.setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED);
            request.setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, name);
            ((DownloadManager) getSystemService(Context.DOWNLOAD_SERVICE)).enqueue(request);
            Toast.makeText(this, "Saving " + name + " to Downloads", Toast.LENGTH_SHORT).show();
        } catch (Exception e) {
            Toast.makeText(this, "Couldn't download the file.", Toast.LENGTH_SHORT).show();
        }
    }

    // ---- What the pages can ask of the phone -------------------------------

    private class Bridge {
        @JavascriptInterface
        public boolean isApp() {
            return true;
        }

        @JavascriptInterface
        public String serverUrl() {
            return MainActivity.this.serverUrl();
        }

        @JavascriptInterface
        public void retry() {
            runOnUiThread(() -> web.loadUrl(MainActivity.this.serverUrl() + "/"));
        }

        @JavascriptInterface
        public void changeServer() {
            runOnUiThread(MainActivity.this::showServerDialog);
        }

        @JavascriptInterface
        public void print() {
            runOnUiThread(() -> {
                PrintManager printManager = (PrintManager) getSystemService(Context.PRINT_SERVICE);
                String job = "Lawa't Kape " + System.currentTimeMillis();
                PrintDocumentAdapter adapter = web.createPrintDocumentAdapter(job);
                printManager.print(job, adapter, new PrintAttributes.Builder().build());
            });
        }

        /** A number of milliseconds, or a [buzz, pause, buzz, …] list, as navigator.vibrate takes. */
        @JavascriptInterface
        public void vibrate(String pattern) {
            Vibrator vibrator = (Vibrator) getSystemService(Context.VIBRATOR_SERVICE);
            if (vibrator == null || !vibrator.hasVibrator()) {
                return;
            }
            try {
                long[] timings;
                String p = pattern == null ? "" : pattern.trim();
                if (p.startsWith("[")) {
                    JSONArray parts = new JSONArray(p);
                    timings = new long[parts.length() + 1];
                    timings[0] = 0;
                    for (int i = 0; i < parts.length(); i++) {
                        timings[i + 1] = Math.max(0, Math.min(5000, parts.getLong(i)));
                    }
                } else {
                    timings = new long[]{0, Math.max(0, Math.min(5000, Long.parseLong(p)))};
                }
                if (Build.VERSION.SDK_INT >= 26) {
                    vibrator.vibrate(VibrationEffect.createWaveform(timings, -1));
                } else {
                    vibrator.vibrate(timings, -1);
                }
            } catch (Exception ignored) {
            }
        }
    }

    // ---- Lifecycle ------------------------------------------------------

    @Override
    public void onBackPressed() {
        if (web.canGoBack()) {
            web.goBack();
        } else {
            super.onBackPressed();
        }
    }

    @Override
    protected void onSaveInstanceState(Bundle outState) {
        super.onSaveInstanceState(outState);
        web.saveState(outState);
    }

    @Override
    protected void onResume() {
        super.onResume();
        web.onResume();
    }

    @Override
    protected void onPause() {
        CookieManager.getInstance().flush();
        web.onPause();
        super.onPause();
    }

    @Override
    protected void onDestroy() {
        web.destroy();
        super.onDestroy();
    }

    private String appVersion() {
        try {
            return getPackageManager().getPackageInfo(getPackageName(), 0).versionName;
        } catch (Exception e) {
            return "1";
        }
    }
}
