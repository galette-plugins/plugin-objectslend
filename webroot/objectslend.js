/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/* Thumbnails link to their full size picture; show it in a modal instead of
 * leaving the page.
 */
var _lendFullImage = function() {
    $(document).on('click', 'a.fullimage', function(e) {
        e.preventDefault();

        var $modal = $('<div class="ui modal fullimage"><i class="close icon" aria-hidden="true"></i><div class="center aligned image content"><img class="ui fluid image"/></div></div>');
        $modal.find('img')
            .attr('src', $(this).attr('href'))
            .attr('alt', $(this).find('img').attr('alt') || '');

        $('body').append($modal);
        $modal.css('width', 'auto').modal({
            onHidden: function() {
                $(this).modal('hide dimmer').remove();
            }
        }).modal('show');
    });
};

/* Borrow and return form: it can be submitted once a status, and a member when
 * borrowing on behalf of someone, has been chosen.
 */
var _lendTakeForm = function() {
    var $form = $('#form_take_object');
    if ($form.length === 0) {
        return;
    }

    var $submit = $form.find('button[name="valid"]');
    var _validate = function() {
        var valid = $('#status').val() !== '-1';
        if ($form.data('mode') === 'take' && $('#id_adh_input').val() === '') {
            valid = false;
        }
        $submit.toggleClass('disabled', !valid);
    };
    $form.on('change', _validate);
    _validate();

    var $terms = $('#show_terms_elt').addClass('displaynone');
    $('#show_terms').on('change', function() {
        $terms.toggleClass('displaynone', !this.checked);
    });

    var $comments = $('#comments');
    $comments.on('input', function() {
        $('#remaining').text($comments.attr('maxlength') - $comments.val().length);
    });
};

/* Preferences: contribution fields only matter when a contribution is generated */
var _lendPreferences = function() {
    $('#auto_generate_contribution').on('change', function() {
        var generate = $(this).is(':checked');
        $('#generated_contribution_fields').toggleClass('displaynone', !generate)
            .find('input[name="pref_objectslend_generated_contribution_type_id"], #contrib_text')
            .prop('required', generate);
    });
};

$(function() {
    _lendFullImage();
    _lendTakeForm();
    _lendPreferences();
});
