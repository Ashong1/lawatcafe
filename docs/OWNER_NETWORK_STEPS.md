# Network steps the owner does by hand

Two changes the app can't make itself: the app's Pi-hole password can read
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
