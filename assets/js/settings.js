jQuery(document).ready(function ($) {
    // Add new category
    $('.add-category').on('click', function () {
        const timestamp = Date.now();
        const template = `
            <div class="category-item">
                <input type="text" 
                       name="categories[new-${timestamp}][name]" 
                       placeholder="Category Name"
                       class="category-name">
                <input type="text" 
                       name="categories[new-${timestamp}][slug]" 
                       placeholder="category-slug"
                       class="category-slug" readonly>
                <button type="button" class="button remove-category">Remove</button>
            </div>
        `;
        $('#product-categories').append(template);
    });

    // Remove category
    $(document).on('click', '.remove-category', function () {
        const $item = $(this).closest('.category-item');
        // Only remove if it's not the last category
        if ($('.category-item').length > 1) {
            $item.remove();
        } else {
            alert('You must keep at least one category.');
        }
    });

    // Auto-generate slug for new categories only
    $(document).on('input', '.category-name', function () {
        const $row = $(this).closest('.category-item');
        const $slugInput = $row.find('.category-slug');

        // Only generate slug if it's not disabled (new category)
        if (!$slugInput.prop('disabled')) {
            $slugInput.val(generateSlug($(this).val()));
        }
    });

    // Prevent removal of disabled categories (existing ones)
    $(document).on('click', '.remove-category', function () {
        const $row = $(this).closest('.category-item');
        const $slugInput = $row.find('.category-slug');

        if ($slugInput.prop('disabled')) {
            alert('Cannot remove default categories.');
            return false;
        }
    });

    // Function to generate slug
    function generateSlug(text) {
        return text.toLowerCase()
            .trim()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/(^-|-$)+/g, '');
    }

    // Form validation before submit
    $('form').on('submit', function (e) {
        let isValid = true;
        const usedSlugs = new Set();

        $('.category-item').each(function () {
            const $name = $(this).find('input[name$="[name]"]');
            const $slug = $(this).find('input[name$="[slug]"]');
            const nameVal = $name.val().trim();
            const slugVal = $slug.val().trim();

            // Check for empty values
            if (!nameVal || !slugVal) {
                alert('Category name and slug are required for all categories.');
                isValid = false;
                return false;
            }

            // Check for duplicate slugs
            if (usedSlugs.has(slugVal)) {
                alert('Duplicate category slugs are not allowed: ' + slugVal);
                isValid = false;
                return false;
            }
            usedSlugs.add(slugVal);
        });

        if (!isValid) {
            e.preventDefault();
        }
    });
});