if (window.jQuery) {
    jQuery(function () {
        jQuery(window).on('resize', function () {
            var pptrack = jQuery('.pptrack-wrapper');
            var width = pptrack.width();
            var cssclass = (width > 756) ? 'wrapperWide' : 'wrapperNarrow';
            pptrack.prop('class', 'pptrack-wrapper ' + cssclass);
        });
        jQuery(window).trigger('resize');
    });
}