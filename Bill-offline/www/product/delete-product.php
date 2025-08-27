function confirmDelete(id) {
    if (confirm("Are you sure you want to delete this product?")) {
        // Redirect to delete-product.php with the product id
        window.location.href = 'delete-product.php?id=' + id;
    }
}