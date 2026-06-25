        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Global Fix for Bootstrap Modals in Nested Layouts
document.addEventListener('DOMContentLoaded', function() {
    // Move all modals to body to ensure they are on the top stacking context
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        document.body.appendChild(modal);
    });
});
</script>

</body>
</html>
