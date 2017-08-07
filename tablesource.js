 var $table = $('table');
      $('button.change-sort').on('click', function () {
          var $this = $(this);
          if ($this.data('custom')) { $.bootstrapSortable(true, undefined, customSort); } else { $.bootstrapSortable(true, undefined, 'default'); }
      });
      $table.on('sorted');
      $('#event').on('change', function () {
          var $this = $(this);
          if ($this.is(':checked')) {
              $table.on('sorted');
          }
          else {
              $table.off('sorted');
          }
      });