window.jQuery(document).ready(function ($) {
  // Manage visit cookie
  let subscriber_id = parseInt($.cookie('ck_subscriber_id')) ? parseInt($.cookie('ck_subscriber_id')) : false

  if (!subscriber_id) {
    subscriber_id = ckGetQueryVariable('ck_subscriber_id')
  }

  // Only POST to admin-ajax.php if we have a subscriber_id to use...
  // ...and the current post has a tag assigned to it
  if (subscriber_id && ck_data.post_has_tag) {
    /* Check if subscriber_id is valid and maybe do add tags */
    $.ajax({
      type: 'POST',
      data: {
        action: 'ck_add_user_visit',
        subscriber_id: subscriber_id,
        url: document.URL
      },
      url: ck_data.ajaxurl,
      success: function (response) {
        const values = JSON.parse(response)
        if (values.subscriber_id === 0) {
          $.cookie('ck_subscriber_id', values.subscriber_id, { expires: 365, path: '/' })
          ckRemoveSubscriberId(window.location.href)
        }
      }

    }).fail(function (response) {
      if (window.console && window.console.log) {
        if (response.responseText) {
          console.log('Error: ' + response.responseText)
        } else {
          console.log('AJAX ERROR' + response)
        }
      }
      ckRemoveSubscriberId(window.location.href)
    })
  }

  /**
     * This function will check for the `ck_subscriber_id` query parameter
     * and if it exists return the value and remove it from the URL.
     *
     * @param variable
     * @returns {*}
     */
  function ckGetQueryVariable (variable) {
    const query = window.location.search.substring(1)
    const vars = query.split('&')
    for (let i = 0; i < vars.length; i++) {
      const pair = vars[i].split('=')
      if (pair[0] === variable) {
        return parseInt(pair[1])
      }
    }
    return false
  }

  /**
     * Remove the url subscriber_id url param
     *
     * The 'ck_subscriber_id' should only be set on URLs included on
     * links from a ConvertKit email with no other URL parameters.
     * This function removes the parameters so a customer won't share
     * a URL with their subscriber ID in it.
     *
     * @param url
     */
  function ckRemoveSubscriberId (url) {
    const clean_url = url.substring(0, url.indexOf('?ck_subscriber_id'))
    const title = document.getElementsByTagName('title')[0].innerHTML
    if (clean_url) {
      window.history.pushState(null, title, clean_url)
    }
  }

  /**
     * When a ConvertKit form is submitted grab the email address
     * and do an API call to get the ck_subscriber_id.
     * If found add cookie.
     *
     */
  window.jQuery(document).on('click', '.formkit-submit', function () {
    const email = window.jQuery('input[name=email_address]').val()

    sleep(1000)

    $.ajax({
      type: 'POST',
      data: {
        action: 'ck_get_subscriber',
        email: email
      },
      url: ck_data.ajaxurl,
      success: function (response) {
        const values = JSON.parse(response)

        if (values.subscriber_id !== 0) {
          $.cookie('ck_subscriber_id', values.subscriber_id, { expires: 365, path: '/' })
        }
      }

    }).fail(function (response) {
      if (window.console && window.console.log) {
        console.log('AJAX ERROR' + response)
      }
    })
  })

  /**
     * Utility function to hold off ajax call
     * @param milliseconds
     */
  function sleep (milliseconds) {
    const start = new Date().getTime()
    for (let i = 0; i < 1e7; i++) {
      if ((new Date().getTime() - start) > milliseconds) {
        break
      }
    }
  }
})
