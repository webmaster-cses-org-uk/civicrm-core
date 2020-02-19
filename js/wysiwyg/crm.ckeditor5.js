/*jshint esversion: 6 */
// https://civicrm.org/licensing
(function($, _) {

  CRM.wysiwyg._create = function(item) {
    var deferred = $.Deferred();

    function onReady() {
      var debounce,
        editor = this;

      editor.on('focus', function() {
        $(item).trigger('focus');
      });
      editor.on('blur', function() {
        editor.updateElement();
        $(item).trigger("blur");
        $(item).trigger("change");
      });
      editor.on('insertText', function() {
        $(item).trigger("keypress");
      });
      _.each(['key', 'pasteState'], function(evName) {
        editor.on(evName, function(evt) {
          if (debounce) clearTimeout(debounce);
          debounce = setTimeout(function() {
            editor.updateElement();
            $(item).trigger("change");
          }, 50);
        });
      });
      editor.on('pasteState', function() {
        $(item).trigger("paste");
      });
      // Hide CiviCRM menubar when editor is fullscreen
      editor.on('maximize', function (e) {
        $('#civicrm-menu').toggle(e.data === 2);
      });
      $(editor.element.$).trigger('crmWysiwygCreate', ['ckeditor', editor]);
      deferred.resolve();
    }

    function initialize() {
      $(item).addClass('crm-wysiwyg-enabled');
      const ClassicEditor = require('@ckeditor/ckeditor5-build-classic');
// Or using the CommonJS version:
// const ClassicEditor = require( '@ckeditor/ckeditor5-build-classic' );

      ClassicEditor
        .create( document.querySelector( '#editor' ) )
        .then( editor => {
          window.editor = editor;
        } )
        .catch( error => {
          console.error( 'There was a problem initializing the editor.', error );
        } );
    }

    if ($(item).hasClass('crm-wysiwyg-enabled')) {
      deferred.resolve();
    }
    else if ($(item).length) {
      // Lazy-load ckeditor.js
      if (window.CKEDITOR) {
        initialize();
      } else {
        CRM.loadScript(CRM.config.resourceBase + 'node_modules/@ckeditor/ckeditor5-build-classic/build/ckeditor.js').done(initialize);
      }
    } else {
      deferred.reject();
    }
    return deferred;
  };

})(CRM.$, CRM._);
