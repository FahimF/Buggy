    </div>
    
    <!-- Image Preview Modal -->
    <div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Image Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center p-0 bg-dark">
                    <img id="previewImage" src="" class="img-fluid" alt="Full size preview">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize the Bootstrap modal
            var imageModalElement = document.getElementById('imagePreviewModal');
            if (imageModalElement) {
                var imageModal = new bootstrap.Modal(imageModalElement);
                var previewImage = document.getElementById('previewImage');

                // Function to attach click listeners to images
                function attachImageListeners() {
                    // Select all images in card text (descriptions) and quill editors (comments)
                    var images = document.querySelectorAll('.card-text img, .ql-editor img');
                    
                    images.forEach(function(img) {
                        // Avoid double-binding
                        if (!img.dataset.hasPreviewListener) {
                            img.dataset.hasPreviewListener = 'true';
                            img.addEventListener('click', function() {
                                previewImage.src = this.src;
                                imageModal.show();
                            });
                        }
                    });
                }

                // Initial attachment
                attachImageListeners();

                // If using MutationObserver to watch for new comments added dynamically
                // (Optional but good practice for SPAs or dynamic content)
                // For now, simple page loads work, but if you add comments via AJAX later, call attachImageListeners() again.
            }
        });
    </script>
</body>
</html>
