
document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('form');
    form.onsubmit = function(e) {
        e.preventDefault(); // Estetään lomakkeen oletuslähetys
        var products = []; // Alusta tuotteiden taulukko

        // Oletetaan, että taulukon rivit edustavat tuotteita
        var rows = document.querySelectorAll('table tr:not(:first-child)');
        rows.forEach(function(row, index) {
            // Ota riviltä tarvittavat tiedot
            var tuoteID = row.querySelector('.tuoteID').innerText; // luokka .tuoteID
            var tapahtumapaikka = row.querySelector('.tapahtumapaikka').innerText; // luokka .tapahtumapaikka
            var lohko = row.querySelector('.block').innerText; // luokka .block
            var paiva = row.querySelector('.haettavaPaiva').innerText; // luokka .haettavaPaiva
            var tunti = row.querySelector('.hour').innerText; // luokka .hour
            var hinta = parseFloat(row.querySelector('.hinta').innerText.replace('€', '').trim()) * 100; // Oletetaan, että sinulla on luokka .hinta
            
            // Luo tuoteobjekti sisältäen kaikki tarvittavat kentät
            var product = {
                "id": tuoteID,
                "title": `Tapahtumapaikka: ${tapahtumapaikka}, Lohko: ${lohko}, Päivä: ${paiva}, Tunti: ${tunti}`,
                "count": 1,
                "pretax_price": hinta,
                "tax": 0,
                "price": hinta,
                "type": 1
            };
            products.push(product); // Lisää tuote tuotteiden taulukkoon
        });

        // Tulosta tuotteiden taulukko kehittäjäkonsoliin ja näytä popup
        console.log(products);

        // Muunna tuotteiden taulukko JSON-muotoon ja aseta se piilotetun kentän arvoksi
        var productsJson = JSON.stringify(products);
        document.getElementById('products').value = productsJson;

        // Tarkistetaan, että tuotteiden taulukko ei ole tyhjä ennen lomakkeen lähetystä
        if(products.length > 0){
            form.submit(); // Lähetä lomake ohjelmallisesti
        } else {
        }
    };
});

