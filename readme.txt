=== Mixpakk Futárszolgálat és Webshoplogisztika ===

Plugin Name: MXP Woocommerce Plugin
Developer: Pintér Gergely
Developer URI: https://mxp.hu
Developer Email: it@mxp.hu
Text Domain: mxp
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Version: 1.3.9
Tested up to: 6.1.1

MXP Webshoplogisztika és Futárszolgálat csomagfeladó plugin WooCommerce webshopokhoz

== Description ==

A plugin használatához érvényes szerződéssel kell rendelkezned a Mixpakk Kft-vel.

[Ajánlatkérés] https://mixpakk.hu/kapcsolat/


A bővítmény a Mav-IT Deliveo szoftverrel való összekötést biztosítja, így annak szolgáltatására támaszkodik (deliveo.eu).
Bővebb információ a Deliveoról: https://deliveo.eu/hu/
A Mav-IT Kft. Adatkezelési Tájékoztatója: https://mav-it.hu/adatkezelesi-tajekoztato

= Kompatibilis Csomagpont pluginek =

* [Csomagpontok és szállítási címkék WooCommerce-hez](https://hu.wordpress.org/plugins/hungarian-pickup-points-for-woocommerce/)
* [PICKPACK CSOMAGPONT BŐVÍTMÉNY](https://weboldalneked.eu/termek/pickpack-csomagpont-bovitmeny/)
* [Sprinter – Pick Pack Pont integráció](https://wordpress.org/plugins/sprinter-pick-pack-pont-integracio/)

== Installation ==

1. Töltsed le a [legfrissebb verziót](https://github.com/Mixpakk-Kft/mxp_woocommerce_plugin/releases/latest)
2. Telepítsd és kapcsold be a bővítményt a Wordpress Plugin kezelő felületén
3. Add meg a csatlakozáshoz szükséges adatokat a WooCommerce -> Mixpakk menüpontban
4. Add fel rendeléseidet a WooCommerce -> Rendelések menüben

== Changelog ==

= 1.3.9 =
* Tömeges címkenyomtatás lehetősége, lap formátum beállítással. <=25 címke nyomtatási előnézetbe kerül, 25nél több címkét lementi pdf fájlokba 25ösével.
* Sprinter PPP integrációs csomagpont rendelések nem jelentek meg a csomagpont szállítási opcióval alapértelmezetten.
* Deliveo március 31 update fix.

= 1.3.8 =
* A filterek hibakezelési funkcionalitásának kiegészítése.

= 1.3.7 =
* Deliveoval való kommunikációs hiba esetén nem kerül a rendelés "Hibás Adat" állapotba többé.
* Webhook kompatibilitás javítás(metaadat módosítás már küld webhook eventet feladás után, akkor is ha nincs státusz módosítás).
* Jobb kompatibilitás WPC Product Bundles és WooCommerce Product Bundles pluginekkel(magát a bundle cikk bejegyzést nem adja tovább).
* WordPress filter alapú címadat és terméklista lekérdezés feladáshoz, ezekre más pluginek is rákapcsolódhatnak az `add_filter` függvény hívással a `mixpakk_order_filter_items` és `mixpakk_order_filter_shipping_data` azonosítókkal. A szűrők paraméterként a feldolgozandó adattömböt és a rendelés objektumát kapják meg.
* Sprinter Pick Pack Pont Integráció plugin kompatibilitás hozzáadása.
* Deliveo-készlethiány esetén opcionálisan automatikus küldemény törlés, illetve külön státuszba is kerül(az új státusz: Nincs készleten).
* Egyéb javítások.

= 1.3.6 =
* Tétel súlyátadási bug javítása.

= 1.3.5 =
* Automatikus frissítés funkció.

= 1.3.3 =
* Alapértelmezett, Csomagpont, Külföldi szállítási opciók beállításához lehetőség.
* Bugfix.

= 1.3.2 =
* WooCommerce státuszok hozzáadása: Kiszállítás alatt, Sikertelen kézbesítés, Hibás adat.
* Hibás/sikertelen feladás esetén a rendelés "Hibás adat" állapotba kerül, rendelés megjegyzésénél a hiba oka tárolva.
* Párhuzamos feladásnál duplikációk megszűntetése és figyelmeztető üzenet visszajelzés.
* Felhasználó nem tudja félbeszakítani a feladást, stabilabb és megbízhatóbb működés érdekében.