jQuery(document).on('click', '.remote_destination_connect_to_dropbox', function()
{
    var data = {
        action:'mainwp_remotedestination_dropbox_connect',
        search: name
    };

    jQuery.ajax({
      type: 'POST',
      url: ajaxurl,
      data: data,
      success: function(pElement) { return function(response)
          {
              pElement.parent().find('input[name="tmp_token"]').val(response['requestToken']['oauth_token']);
              pElement.parent().find('input[name="tmp_token_secret"]').val(response['requestToken']['oauth_token_secret']);
              window.open(response['authorizeUrl'], '_blank');
              pElement.hide();
              pElement.parent().find('.remote_destination_connect_to_dropbox_authorized').show();
          }}(jQuery(this)),
      dataType: 'json'
    });
});

jQuery(document).on('click', '.remote_destination_connect_to_dropbox_authorized', function()
{
    var data = {
        action:'mainwp_remotedestination_dropbox_authorize',
        token: jQuery(this).parent().find('input[name="tmp_token"]').val(),
        token_secret: jQuery(this).parent().find('input[name="tmp_token_secret"]').val()
    };

    jQuery.ajax({
      type: 'POST',
      url: ajaxurl,
      data: data,
      success: function(pElement) { return function(response)
          {
              if (response['error'] != undefined)
              {
                  alert(response['error'] + '\n'+__('Please reconnect to Dropbox'));
                  pElement.hide();
                  pElement.parent().find('.remote_destination_connect_to_dropbox').show();
                  return;
              }

              pElement.parent().find('input[name="token"]').val(response['accessToken']['oauth_token']);
              pElement.parent().find('input[name="token_secret"]').val(response['accessToken']['oauth_token_secret']);
              pElement.hide();
              jQuery(pElement.parents('.backup_destination_settings_panel')[0]).find('.backup_destination_new_save').show();
              jQuery(pElement.parents('.backup_destination_settings_panel')[0]).find('.backup_destination_test').show();
          }}(jQuery(this)),
      dataType: 'json'
    });
});

jQuery(document).on('click', '.remote_destination_reconnect_to_dropbox', function()
{
    var data = {
        action:'mainwp_remotedestination_dropbox_connect'
    };

    jQuery.ajax({
      type: 'POST',
      url: ajaxurl,
      data: data,
      success: function(pElement) { return function(response)
          {
              pElement.parent().find('input[name="tmp_new_token"]').val(response['requestToken']['oauth_token']);
              pElement.parent().find('input[name="tmp_new_token_secret"]').val(response['requestToken']['oauth_token_secret']);
              window.open(response['authorizeUrl'], '_blank');
              pElement.hide();
              pElement.parent().find('.remote_destination_reconnect_to_dropbox_authorized').show();
          }}(jQuery(this)),
      dataType: 'json'
    });
});

jQuery(document).on('click', '.remote_destination_reconnect_to_dropbox_authorized', function()
{
    var data = {
        action:'mainwp_remotedestination_dropbox_authorize',
        token: jQuery(this).parent().find('input[name="tmp_new_token"]').val(),
        token_secret: jQuery(this).parent().find('input[name="tmp_new_token_secret"]').val()
    };

    jQuery.ajax({
      type: 'POST',
      url: ajaxurl,
      data: data,
      success: function(pElement) { return function(response)
          {
              if (response['error'] != undefined)
              {
                  alert(response['error'] + '\n'+__('Please reconnect to Dropbox'));
                  pElement.hide();
                  pElement.parent().find('.remote_destination_reconnect_to_dropbox').show();
                  return;
              }

              pElement.parent().find('input[name="new_token"]').val(response['accessToken']['oauth_token']);
              pElement.parent().find('input[name="new_token_secret"]').val(response['accessToken']['oauth_token_secret']);
              pElement.hide();
          }}(jQuery(this)),
      dataType: 'json'
    });
});


jQuery(document).on('click', '.remote_destination_connect_to_copy', function()
{
    console.log('test?');
    var data = {
        action:'mainwp_remotedestination_copy_connect',
        search: name
    };

    jQuery.ajax({
      type: 'POST',
      url: ajaxurl,
      data: data,
      success: function(pElement) { return function(response)
          {
              pElement.parent().find('input[name="tmp_token"]').val(response['requestToken']['oauth_token']);
              pElement.parent().find('input[name="tmp_token_secret"]').val(response['requestToken']['oauth_token_secret']);
              window.open(response['authorizeUrl'], '_blank');
              pElement.hide();
              pElement.parent().find('.remote_destination_connect_to_copy_authorized').show();
          }}(jQuery(this)),
      dataType: 'json'
    });
});

jQuery(document).on('click', '.remote_destination_connect_to_copy_authorized', function()
{
    var data = {
        action:'mainwp_remotedestination_copy_authorize',
        token: jQuery(this).parent().find('input[name="tmp_token"]').val(),
        token_secret: jQuery(this).parent().find('input[name="tmp_token_secret"]').val()
    };

    jQuery.ajax({
      type: 'POST',
      url: ajaxurl,
      data: data,
      success: function(pElement) { return function(response)
          {
              if (response['error'] != undefined)
              {
                  alert(response['error'] + '\n'+__('Please reconnect to Copy.com'));
                  pElement.hide();
                  pElement.parent().find('.remote_destination_connect_to_copy').show();
                  return;
              }

              pElement.parent().find('input[name="token"]').val(response['accessToken']['oauth_token']);
              pElement.parent().find('input[name="token_secret"]').val(response['accessToken']['oauth_token_secret']);
              pElement.hide();
              jQuery(pElement.parents('.backup_destination_settings_panel')[0]).find('.backup_destination_new_save').show();
              jQuery(pElement.parents('.backup_destination_settings_panel')[0]).find('.backup_destination_test').show();
          }}(jQuery(this)),
      dataType: 'json'
    });
});

jQuery(document).on('click', '.remote_destination_reconnect_to_copy', function()
{
    var data = {
        action:'mainwp_remotedestination_copy_connect'
    };

    jQuery.ajax({
      type: 'POST',
      url: ajaxurl,
      data: data,
      success: function(pElement) { return function(response)
          {
              pElement.parent().find('input[name="tmp_new_token"]').val(response['requestToken']['oauth_token']);
              pElement.parent().find('input[name="tmp_new_token_secret"]').val(response['requestToken']['oauth_token_secret']);
              window.open(response['authorizeUrl'], '_blank');
              pElement.hide();
              pElement.parent().find('.remote_destination_reconnect_to_copy_authorized').show();
          }}(jQuery(this)),
      dataType: 'json'
    });
});

jQuery(document).on('click', '.remote_destination_reconnect_to_copy_authorized', function()
{
    var data = {
        action:'mainwp_remotedestination_copy_authorize',
        token: jQuery(this).parent().find('input[name="tmp_new_token"]').val(),
        token_secret: jQuery(this).parent().find('input[name="tmp_new_token_secret"]').val()
    };

    jQuery.ajax({
      type: 'POST',
      url: ajaxurl,
      data: data,
      success: function(pElement) { return function(response)
          {
              if (response['error'] != undefined)
              {
                  alert(response['error'] + '\n'+__('Please reconnect to Copy.com'));
                  pElement.hide();
                  pElement.parent().find('.remote_destination_reconnect_to_copy').show();
                  return;
              }

              pElement.parent().find('input[name="new_token"]').val(response['accessToken']['oauth_token']);
              pElement.parent().find('input[name="new_token_secret"]').val(response['accessToken']['oauth_token_secret']);
              pElement.hide();
          }}(jQuery(this)),
      dataType: 'json'
    });
});



jQuery(document).on('click', '.backup_destination_test', function(event)
{
    managebackups_destination_test(jQuery(this));
    return false;
});
managebackups_destination_test = function(pElement)
{
    var destinationContainer = jQuery(pElement).parent().parent();
    var taskId = destinationContainer.find('.remote_destination_id').val();
    var taskType = destinationContainer.find('.remote_destination_type').val();
    var settingsPanel = destinationContainer.find('.backup_destination_settings_panel');
    var allInputs = {};
    settingsPanel.find('input').each(function() {
        if (jQuery(this).attr('type') == 'checkbox')
        {
            allInputs[this.name] = (jQuery(this).is(':checked') ? 1 : 0);
        }
        else
        {
            allInputs[this.name] = this.value;
        }
    });

    destinationContainer.find('.backup_destination_test .text').html(__('Testing ...'));
    destinationContainer.find('.backup_destination_test .loading').show();
    destinationContainer.find('.backup_destination_test').attr("disabled", true);

    var data = {
        action:'mainwp_remote_dest_test',
        taskId:taskId,
        taskType:taskType,
        fields: allInputs,
        security: mainwp_remote_backup_security_nonces['mainwp_remote_dest_test']
    };
    jQuery.post(ajaxurl, data, function(pDestinationContainer) { return function (response) {
        pDestinationContainer.find('.backup_destination_test .text').html(__('Test Settings'));
        pDestinationContainer.find('.backup_destination_test .loading').hide();
        pDestinationContainer.find('.backup_destination_test').removeAttr("disabled");

        if (response.error)
        {
          alert(response.error);
        }
        else if (response.information)
        {
            alert(response.information);
        }
        else
        {
            alert(__('Received wrong response from the server.'));
        }
    } }(destinationContainer), 'json');
};

jQuery(document).ready(function () {
    jQuery('#addremotebackupdestination').live('click', function(event)
    {
       jQuery('#remote_backup_destination_dialog').dialog({
           resizable: false,
           height: 520,
           width: 600,
           modal: true
       });
    });
    jQuery('.backup_destination_type').live({
        mouseenter:
           function()
           {
               if (jQuery(this).parent().hasClass('settings'))
                {
                   jQuery(this).parent().find('.backup_destination_type').slice(0, 1).css('color', 'red');
                   jQuery(this).parent().find('.backup_destination_title').slice(0, 1).css('color', 'red');
               }
           },
        mouseleave:
           function()
           {
               jQuery(this).parent().find('.backup_destination_type').slice(0, 1).css('color', '');
              jQuery(this).parent().find('.backup_destination_title').slice(0, 1).css('color', '');
           }
   });
    jQuery('.backup_destination_title').live({
            mouseenter:
               function()
               {
                   if (jQuery(this).parent().hasClass('settings'))
                   {
                       jQuery(this).parent().find('.backup_destination_type').slice(0, 1).css('color', 'red');
                       jQuery(this).parent().find('.backup_destination_title').slice(0, 1).css('color', 'red');
                   }
               },
            mouseleave:
               function()
               {
                   jQuery(this).parent().find('.backup_destination_type').slice(0, 1).css('color', '');
                  jQuery(this).parent().find('.backup_destination_title').slice(0, 1).css('color', '');
               }
       });
    jQuery('.backup_destination_type').live('click', function(event) { managebackups_remote_dest_clicked(event, jQuery(this).parent()); });
    jQuery('.backup_destination_title').live('click', function(event) { managebackups_remote_dest_clicked(event, jQuery(this).parent()); });

    jQuery('#remote_backup_destination_dialog-close').live('click', function(event)
    {
        jQuery('#remote_backup_destination_dialog').dialog('destroy');
    });
    jQuery('.backup_destination_cont').live('click', function(event)
    {

    });
    jQuery('.backup_destination_settings_open').live('click', function(event)
    {
        var settingsPanel = jQuery(this).parent().parent().find('.backup_destination_settings_panel');
        if (settingsPanel.is(":visible")) settingsPanel.hide();
        else
        {
            settingsPanel.parent().parent().find('.backup_destination_new_cont_panel').hide();
            settingsPanel.parent().parent().find('.backup_destination_settings_panel').hide();
            settingsPanel.show();
        }
    });
});
jQuery(document).on('click', '.backup_destination_add_new', function(event)
{
    var panelToOpen = jQuery(this).parent().parent().find('.backup_destination_settings_panel');
    if (panelToOpen.is(":visible")) panelToOpen.hide();
    else
    {
        panelToOpen.parent().parent().find('.backup_destination_settings_panel').hide();
        panelToOpen.show();
    }
});
jQuery(document).on('click', '.backup_destination_save', function(event)
{
    managebackups_destination_save(jQuery(this));
    return false;
});
jQuery(document).on('click', '.backup_destination_new_save', function(event)
{
    backup_destination_new_save(jQuery(this));
    return false;
});
jQuery(document).on('click', '.backup_destination_delete', function(event)
{
    managebackups_destination_delete(jQuery(this));
    return false;
});

managebackups_destination_save = function(pElement)
{
    var destinationContainer = jQuery(pElement).closest('.backup_destination_cont');
    var taskId = destinationContainer.find('.remote_destination_id').val();
    var taskType = destinationContainer.find('.remote_destination_type').val();
    var settingsPanel = destinationContainer.find('.backup_destination_settings_panel');
    var allInputs = {};
    settingsPanel.find('input').each(function() {
        if (jQuery(this).attr('type') == 'checkbox')
        {
            allInputs[this.name] = (jQuery(this).is(':checked') ? 1 : 0);
        }
        else
        {
            allInputs[this.name] = this.value;
        }
    });

    if (allInputs['title'] == '') allInputs['title'] = __('Untitled');

    var data = {
        action:'mainwp_remote_dest_save',
        taskId:taskId,
        taskType:taskType,
        fields: allInputs,
        security: mainwp_remote_backup_security_nonces['mainwp_remote_dest_save']
    };
    jQuery.post(ajaxurl, data, function(pTaskId, pFields) { return function (response) {
        response = jQuery.trim(response);
        var obj = jQuery.parseJSON(response);
        if (obj.error)
        {
          alert(obj.error);
        }
        else if (obj.information)
        {
            jQuery('input.remote_destination_id[value="'+pTaskId+'"]').parent().find('.backup_destination_title').html(pFields['title']);
            alert(obj.information);
        }
        else
        {
            alert(__('Received wrong response from the server.'));
        }
    } }(taskId, allInputs));
};
backup_destination_new_save = function(pElement)
{
    var destinationContainer = jQuery(pElement).parent().parent();
    var taskType = destinationContainer.find('.remote_destination_type').val();
    var settingsPanel = destinationContainer.find('.backup_destination_settings_panel');
    var allInputs = {};
    settingsPanel.find('input').each(function() {
        if (jQuery(this).attr('type') == 'checkbox')
        {
            allInputs[this.name] = (jQuery(this).is(':checked') ? 1 : 0);
        }
        else
        {
            allInputs[this.name] = this.value;
        }
    });

    if (allInputs['title'] == '') allInputs['title'] = __('Untitled');

    var data = {
        action:'mainwp_remote_dest_save',
        taskType:taskType,
        fields: allInputs,
        security: mainwp_remote_backup_security_nonces['mainwp_remote_dest_save']
    };
    jQuery.post(ajaxurl, data, function(pSettingsPanel) { return function (response) {
        response = jQuery.trim(response);
        var obj = jQuery.parseJSON(response);
        if (obj.error)
        {
          alert(obj.error);
        }
        else if (obj.information)
        {
            if (obj.newEl)
            {
                var newEl = jQuery(obj.newEl);
                newEl.insertBefore(jQuery('#backup_destination_new_add_here'));
            }
            jQuery('html,body').animate({scrollTop: newEl.offset().top},'slow');
            pSettingsPanel.hide();
            newEl.find('.backup_destination_settings_panel').show();

            alert(obj.information);
            managebackups_remote_dest_clicked(undefined, newEl);
        }
        else
        {
            alert(__('Received wrong response from the server.'));
        }
    } }(settingsPanel));
};
managebackups_destination_delete = function(pElement)
{
    var res = confirm(__('Are you sure you want to remove this destination. This could make some of the backup tasks invalid.'));
    if (!res) return;

    var destinationContainer = jQuery(pElement).closest('.backup_destination_cont');
    var taskId = destinationContainer.find('.remote_destination_id').val();

    var data = {
        action:'mainwp_remote_dest_delete',
        taskId:taskId,
        security: mainwp_remote_backup_security_nonces['mainwp_remote_dest_delete']
    };
    jQuery.post(ajaxurl, data, function(pTaskId) { return function (response) {
        response = jQuery.trim(response);
        var obj = jQuery.parseJSON(response);
        if (obj.error)
        {
          alert(obj.error);
        }
        else if (obj.information)
        {
            jQuery('input.remote_destination_id[value="'+pTaskId+'"]').parent().remove();
            alert(obj.information);
        }
        else
        {
            alert(__('Received wrong response from the server.'));
        }
    } }(taskId));
};
managebackups_remote_dest_clicked = function(event, pElement)
{
    if (!pElement.hasClass('settings')) return;
    if (pElement.hasClass('new'))
    {
        var newContPanel = pElement.find('.backup_destination_new_cont_panel');
        if (newContPanel.is(":visible")) newContPanel.hide();
        else
        {
            pElement.parent().find('.backup_destination_settings_panel').hide();
            newContPanel.show();
        }
        return;
    }

    jQuery('#remote_backup_destination_dialog').dialog('destroy');
    var value = pElement.find('input.remote_destination_id').val();
    if (jQuery('#backup_destination_list').find('input.remote_destination_id[value="'+value+'"]').length > 0) return;

    var newEl = pElement.clone();
    newEl.removeClass('settings');
    newEl.find('.backup_destination_settings').remove();
    newEl.find('.backup_destination_settings_panel').remove();
    var excludeElement = jQuery('#backup_destination_list').find('.backup_destination_excludecont.template').clone();
    excludeElement.removeClass('template');
    excludeElement.css('display', '');
    var inputElement = newEl.find('input.remote_destination_id');
    inputElement.attr('name', inputElement.attr('name').replace('template', ''));
    newEl.append(excludeElement);
    jQuery('#backup_destination_list').append(newEl);
};