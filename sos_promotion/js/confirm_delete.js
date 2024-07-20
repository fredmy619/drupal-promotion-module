(function ($, Drupal) {
  Drupal.behaviors.confirmDelete = {
    attach: function (context, settings) {
      $('.delete-promotion-button', context).each(function () {
        var $button = $(this);

        if (!$button.hasClass('confirm-delete-attached')) {
          $button.addClass('confirm-delete-attached');
          $button.on('click', function (event) {
            if (!confirm('Are you sure you want to delete this promotion?')) {
              event.preventDefault();
            }
          });
        }
      });
    }
  };
})(jQuery, Drupal);
