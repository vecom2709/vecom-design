# Vecom Partner — Android-App

Trusted Web Activity um `https://vecom-design.it/partner.php?app=1`. Kein eigener
Code: Chrome zeigt die Partnerseite im Vollbild, mit derselben Anmeldung, denselben
Hinweisen (Web-Push) und demselben bestätigten Gerät wie im Browser. Eine Änderung
an `partner.php` braucht deshalb **keine** neue APK.

Neu bauen nur, wenn sich Symbol, Farben, Startadresse oder Paketname ändern:

```bash
# einmalig: Android-SDK (platforms;android-36, build-tools;36.0.0), sdk.dir in local.properties
VECOM_KEYSTORE=/sicherer/ort/vecom-partner.jks VECOM_KEYSTORE_PASS=… \
  gradle assembleRelease
cp app/build/outputs/apk/release/app-release.apk ../assets/app/vecom-partner.apk
```

Vorher `versionCode` in `app/build.gradle` um eins erhöhen — sonst lehnt Android
das Update ab.

**Der Schlüssel `vecom-partner.jks` liegt nie im Repository.** Sein SHA-256 steht in
`/.well-known/assetlinks.json`. Passen beide nicht zusammen, läuft die App weiter,
zeigt aber oben wieder eine Adresszeile. Geht der Schlüssel verloren, kann keine
installierte App mehr aktualisiert werden — jeder Partner müsste neu installieren.

iPhone: Apple lässt keine App-Datei außerhalb von App Store/TestFlight zu. Dort gibt
es `assets/app/vecom-partner.mobileconfig` — ein Profil mit Web-Clip (Vollbild-Symbol
auf dem Home-Bildschirm). Es ist nicht signiert; iOS zeigt deshalb „Nicht überprüft“.
