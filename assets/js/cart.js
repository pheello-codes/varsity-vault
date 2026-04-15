// Cart functionality using database

// Update cart count in navbar
function updateCartCount() {
    fetch('/varsity-vault/cart-count.php')
        .then(response => response.json())
        .then(data => {
            document.getElementById('cart-count').textContent = data.count || 0;
        })
        .catch(error => console.error('Error updating cart count:', error));
}

// Add item to cart
function addToCart(noteId, title, price) {
    const formData = new FormData();
    formData.append('action', 'add_to_cart');
    formData.append('note_id', noteId);
    formData.append('quantity', 1);

    fetch('/varsity-vault/cart.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (response.ok) {
            updateCartCount();
            alert('Item added to cart!');
        } else {
            alert('Error adding item to cart. Please try again.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error adding item to cart. Please try again.');
    });
}

// Initialize cart count on page load
document.addEventListener('DOMContentLoaded', function() {
    updateCartCount();
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', updateCartCount);