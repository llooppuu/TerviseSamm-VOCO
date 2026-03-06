# TerviseSamm – funktsionaalsuse kirjeldus

## Mis on TerviseSamm?

TerviseSamm (Vormipäevik) on rakendus, mis võimaldab õpilastel jälgida oma füüsilist arengut ja saada sõbralikku tagasisidet. Õpetajad saavad näha rühmade ülevaadet ning boss-õpetaja hallata, kellel on ligipääs millistele rühmadele. Rakenduse toon on toetav ja arengukeskne – eesmärk on motiveerida, mitte jälgida ega kontrollida.

---

## Õpilase võimalused

### Oma andmete sisestamine

Õpilane saab igal päeval teha ühe sissekande, kuhu võib lisada:

- **Kuupäev** (vaikimisi täna)
- **Kaal** (valikuline – ainult siis, kui tahad)
- **Kätekõverdused** (valikuline)
- **Märkus** (vabatekst kuni 300 tähemärki)

Vähemalt üks välja peab olema täidetud – ei pea sisestama kõike korraga.

### Oma andmete vaatamine ja muutmine

Õpilane näeb oma sissekannete ajalugu (viimased 90 päeva) ja saab vajadusel andmeid muuta või kustutada. Iga päev saab olla ainult üks sissekanne.

### AI tagasiside

Pärast sissekande salvestamist genereerib süsteem automaatselt lühikese, motiveeriva tagasiside. Tagasiside:

- on 2–4 lauset
- on sõbralik ja ei moraliseeri
- ei maini kaalu numbrina
- annab ühe konkreetse järgmise sammu
- keskendub võimalusel järjepidevusele ja väikesele arengule

Tagasiside salvestatakse, et seda saaks hiljem uuesti vaadata.

### Graafikud

Õpilane näeb oma arengu graafikuid:

- kätekõverduste muutumine ajas
- kaalu kõver (kui kaal on sisestatud)

Graafikud kuvavad viimase 90 päeva andmeid.

---

## Õpetaja võimalused

### Lubatud rühmad

Õpetaja näeb ainult neid rühmi (nt ITA25, ITS25), millele boss-õpetaja talle ligipääsu andnud on. Teisi rühmi ta ei näe.

### Rühma koond

Rühma valimisel kuvatakse:

- **Osalus** – mitu õpilast on sisestanud viimase 14 päeva jooksul (võrreldes kogu rühma suurusega)
- **Keskmine kätekõverduste arv** – iga õpilase viimase sissekande põhjal
- **Muutus** – kuidas rühma keskmine on viimase ja eelmise sissekande vahel muutunud

### Õpilaste ülevaade

Õpetaja näeb rühma õpilaste tabelit:

- õpilase nimi
- viimase sissekande kuupäev
- viimane kätekõverduste arv
- **trend-indikaator**:
  - **roheline** – paraneb (võrreldes eelmisega)
  - **kollane** – stabiilne
  - **punane** – langeb või pole viimase 14 päeva jooksul sisestanud

Õpetaja ei saa õpilaste sisestusi muuta ega kustutada.

### Õpilase lähiajalugu

Vajadusel saab õpetaja vaadata üksiku õpilase viimaseid 5 sissekannet – väike ülevaade, mitte kogu ajalugu.

---

## Boss-õpetaja (admin) võimalused

### Õpetajate haldamine

Boss-õpetaja saab:

- luua uusi õpetaja kontosid (nimi, e-mail/kasutajanimi, ajutine parool)
- õpetajaid aktiveerida või deaktiveerida
- taastada õpetajate paroole (genereeritakse uus ajutine parool)

### Ligipääsude määramine

Boss-õpetaja määrab iga õpetaja jaoks, millistele rühmadele tal ligipääs on. Näiteks üks õpetaja näeb ainult ITA25 ja ITS25, teine vaid ITA24. Ligipääsu saab anda ja võtta tagasi.

### Rühmade haldamine

Boss-õpetaja saab:

- luua uusi rühmi (kood nt ITA25, valikuline inimloetav nimi)
- rühmi muuta ja deaktiveerida
- lisada õpilasi rühmadesse ja eemaldada neid

---

## Privaatsus ja andmekaitse

- **Õpilane** näeb ainult oma andmeid. Teiste õpilaste sisestusi ta ei saa vaadata.
- **Õpetaja** näeb ainult neid rühmi ja neis olevaid õpilasi, millele tal ligipääs on. Ta ei saa näha rühmi, millele tal ligipääsu ei ole.
- **Boss-õpetaja** näeb kõike, kuid seda kasutab halduse ja ligipääsude korraldamiseks.
- Kaalu andmed on tundlikud: AI tagasiside ei maini kaalu numbrina avalikult.
- Kõik olulised tegevused (sisselogimine, sissekannete muutmine, ligipääsude andmine jms) logitakse auditisse.

---

## Sisselogimine

Kõik kasutajad logivad sisse oma kasutajanime (nt e-mail) ja parooliga. Pärast sisselogimist suunatakse automaatselt vastavasse vaatesse:

- õpilane → oma areng
- õpetaja → rühmad
- boss-õpetaja → haldus
