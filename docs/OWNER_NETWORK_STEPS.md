# Network steps the owner does by hand

Changes the app can't make itself: the app's Pi-hole password can read
settings but not change them, and firewall rule changes on OPNsense are
kept manual on purpose. Each step lists how to check it worked and how to undo it.

## 1. Pi-hole: add the portal's `.localdomain` name (2 minutes)

**Why:** some phones (seen on a Huawei tablet) look up the portal through
their maker's own cloud DNS first. When that fails, Android asks the café's
DNS for `wifi.lawatkape.lab.localdomain`, and Pi-hole currently answers
"doesn't exist". Adding the name makes that retry succeed.

1. Open the Pi-hole admin page (`http://192.168.2.4/admin`) and sign in.
2. Go to **Settings → Local DNS Records**.
3. Domain: `wifi.lawatkape.lab.localdomain`, IP: `192.168.2.100`. Click **Add**.

**Check:** from any shop computer, `nslookup wifi.lawatkape.lab.localdomain 192.168.2.4`
returns `192.168.2.100`.
**Undo:** delete the record on the same page.

## 2. OPNsense: send all guest DNS through Pi-hole (10 minutes)

**Why:** site blocking lives in Pi-hole. A guest who sets their phone's DNS
to 8.8.8.8, or uses an app with its own DNS, skips it. These rules catch
every plain DNS request (port 53) on the LAN and hand it to Pi-hole, and
block encrypted DNS-over-TLS (port 853), which can't be redirected.

Pi-hole (`192.168.2.4`) is on the same LAN as the guests, so a redirect
alone would make Pi-hole answer the phone directly from an address the
phone didn't ask, and the phone would drop the answer. Step C fixes that.

**A. Port forward (Firewall → NAT → Port Forward → Add)**

| Field | Value |
|---|---|
| Interface | LAN |
| Protocol | TCP/UDP |
| Source | LAN net |
| Destination / Invert | ✔ Invert, Single host: `192.168.2.4` |
| Destination port | DNS (53) |
| Redirect target IP | `192.168.2.4` |
| Redirect target port | DNS (53) |
| Description | Force guest DNS through Pi-hole |
| Filter rule association | Add associated filter rule |

Then add a second port forward that is identical except **Source =
Single host `192.168.2.4`** and **No RDR (NOT)** ticked, and move it
**above** the first. Pi-hole's own lookups to the internet must not be
redirected back to itself.

**B. Block DNS-over-TLS (Firewall → Rules → LAN → Add)**

Action **Block**, Protocol **TCP/UDP**, Source **LAN net**, Destination
**any**, Destination port **853**, Description `Block DNS-over-TLS`.

**C. Outbound NAT for the hairpin (Firewall → NAT → Outbound)**

If the mode is *Automatic*, switch to **Hybrid**. Add: Interface **LAN**,
Protocol **TCP/UDP**, Source **LAN net**, Destination **Single host
`192.168.2.4`**, Destination port **53**, Translation **Interface address**,
Description `DNS redirect hairpin`.

Click **Apply changes** after each section.

**Check:** on a phone on the café Wi-Fi, set Private DNS off and a manual
DNS of `8.8.8.8`, then open a site on the Site Blocking list. It should
not load. The Network → Health page's DNS check should stay green.
**Undo:** disable the three rules (tick them off) and Apply.

Not covered: DNS-over-HTTPS (browsers' "secure DNS") looks like normal web
traffic and can't be redirected. Chrome and Firefox turn it off by
themselves when they see a network-provided DNS filter, which covers most
guests.

## 3. Start the shop without internet after a power cut (15 minutes)

**Why:** OPNsense waits for the internet provider's router before it starts
the shop's own network. The firewall log for the 2026-09-28 01:50 boot shows
it: "Configuring WAN interface" at 01:50:32, then nothing until the request
to the ISP router (192.168.254.254) timed out at 01:51:51. Only after that
79-second wait did it start handing out Wi-Fi addresses (Kea), DNS and the
login page. After a power cut the ISP router is still starting up too, so every
phone, the POS and the tablets sit without an address the whole time. With
WAN up, the same step takes about 1 second.

### 3a. OPNsense: stop waiting long for the ISP router

1. Open OPNsense (`https://192.168.2.251`) → **Interfaces → [WAN]**.
2. Under **DHCP client configuration**, set **Configuration Mode** to **Advanced**.
3. Under **Protocol Timing**, enter: Timeout `10`, Retry `30`, Select Timeout `0`,
   Reboot `10`, Backoff Cutoff `30`, Initial Interval `1`.
4. **Save**, then **Apply changes**.

The WAN still picks up its address on its own once the ISP router is ready:
it keeps retrying every 30 seconds in the background.

**Check:** switch off the ISP router (or unplug the cable from its LAN port),
then reboot OPNsense from **Power → Reboot**. Phones should get Wi-Fi
addresses about a minute sooner than before. Turn the ISP router back on: the
Network Health page shows the internet back within a few minutes, without
rebooting anything.
**Undo:** set **Configuration Mode** back to **Basic** and save.

### 3b. Proxmox: start everything by itself, router first

1. In the Proxmox web page, select the OPNsense VM → **Options**.
   - **Start at boot**: Yes.
   - **Start/Shutdown order**: `1`, **Startup delay**: `30` (seconds before the next machine starts).
2. Do the same for Pi-hole (192.168.2.4), Nginx Proxy Manager (192.168.2.5)
   and the app server (192.168.2.100): **Start at boot** Yes, order `2`.
3. In the Proxmox computer's BIOS, set **Restore on AC power loss** (also
   called "AC back" or "Power on after power failure") to **Power On**, so the
   server turns itself back on when the electricity returns.
4. Check that the Proxmox host's own address is set by hand (fixed) in
   **System → Network**, not by DHCP. It must not depend on OPNsense being up.

**Check:** with the internet off, pull the Proxmox power plug and put it back.
Without anyone touching it, the shop Wi-Fi, the POS and the login page should
come back within about 3 minutes. The app shows a yellow "The internet is
down" bar until the internet returns.
**Undo:** set **Start at boot** back to No.

### What works while the internet is down

The register, vouchers, the Wi-Fi login page, reports and staff screens all
run inside the shop. Guests can connect and enter their code; websites load
once the internet is back. Barista AI says plainly that the internet is down
instead of spinning. Purchase-order and shift-audit emails wait in a queue
and go out by themselves once it returns (retried every 5 minutes, for up to
3 days).
