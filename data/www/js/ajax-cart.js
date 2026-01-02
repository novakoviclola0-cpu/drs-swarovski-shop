// js/ajax-cart.js

function updateCartCount(count) {
    const cartLinks = document.querySelectorAll('a[href="kosarica.php"]');
    cartLinks.forEach(link => {
        let text = link.textContent.trim();
        text = text.replace(/\s*\(\d+\)$/, '');
        if (count > 0) {
            link.textContent = text + ` (${count})`;
        } else {
            link.textContent = text;
        }
    });
}

document.querySelectorAll('.add-to-cart-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        formData.append('ajax', '1');

        fetch(this.action, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateCartCount(data.cartCount);
                alert('Izdelek je bil dodan v košarico!');
            } else {
                alert(data.message || 'Napaka pri dodajanju v košarico.');
            }
        })
        .catch(() => {
            alert('Napaka pri komunikaciji s strežnikom.');
        });
    });
});