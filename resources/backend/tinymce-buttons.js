(function (tinymce) {
  tinymce.PluginManager.add('convertkit_button', function (editor, url) {
    editor.addButton('convertkit_button', {
      icon: 'convertkit',
      tooltip: 'ConvertKit Custom Content',
      onclick: function () {
        editor.windowManager.open({
          title: 'Insert custom content shortcode',
          body: [
            {
              type: 'listbox',
              name: 'tag',
              label: 'Tag',
              values: editor.settings.ckTags
            }
          ],
          onsubmit: function (e) {
            editor.insertContent('[convertkit_content tag="' + e.data.tag + '"][/convertkit_content]')
          }
        })
      }
    })
    console.log('added button')
  })
})(tinymce)
