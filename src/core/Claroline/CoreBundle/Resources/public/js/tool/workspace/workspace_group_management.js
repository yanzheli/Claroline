/* global removeGroupConfirm */
/* global groupList */

(function () {
    'use strict';

    $('html, body').animate({
        scrollTop: 0
    }, 0);

    var twigWorkspaceId = document.getElementById('twig-attributes').getAttribute('data-workspaceId'),
        loading = false,
        stop = false,
        mode = 0,
        standardRoute = function () {
            return Routing.generate('claro_workspace_registered_groups_paginated', {
                'workspaceId': twigWorkspaceId,
                'offset': $('.row-group').length
            });
        },
        searchRoute = function () {
            return Routing.generate('claro_workspace_search_registered_groups', {
                'workspaceId': twigWorkspaceId,
                'offset': $('.row-group').length,
                'search': document.getElementById('search-group-txt').value
            });
        };

    function lazyloadGroups(route) {
        loading = true;
        $('#loading').show();
        $.ajax({
            url: route(),
            success: function (groups) {
                $('#group-table-body').append(Twig.render(groupList, {
                    'groups': groups
                }));
                loading = false;
                $('#loading').hide();
                if (groups.lenght === 0) {
                    stop = true;
                }
            },
            complete: function () {
                if ($(window).height() >= $(document).height() && stop === false) {
                    lazyloadGroups(route);
                }
            },
            type: 'GET'
        });
    }

    function initEvents() {
        $('.chk-group').live('change', function () {
            if ($('.chk-group:checked').length) {
                $('.delete-groups-button').removeAttr('disabled');
            } else {
                $('.delete-groups-button').attr('disabled', 'disabled');
            }
        });

        $(window).scroll(function () {
            if  (($(window).scrollTop() + 100 >= $(document).height() - $(window).height()) &&
                loading === false &&
                stop === false) {
                if (mode === 0) {
                    lazyloadGroups(standardRoute);
                } else {
                    lazyloadGroups(searchRoute);
                }
            }
        });

        $('.delete-groups-button').click(function () {
            $('#validation-box').modal('show');
            $('#validation-box-body').html(Twig.render(removeGroupConfirm,
                {'nbGroups': $('.chk-group:checked').length}
            ));
        });

        $('#modal-valid-button').click(function () {
            var parameters = {};
            var array = [];
            var i = 0;
            $('.chk-group:checked').each(function (index, element) {
                array[i] = element.value;
                i++;
            });

            parameters.ids = array;
            var route = Routing.generate('claro_workspace_delete_groups', {'workspaceId': twigWorkspaceId});
            route += '?' + $.param(parameters);
            $('#deleting').show();
            $.ajax({
                url: route,
                success: function () {
                    $('.chk-group:checked').each(function (index, element) {
                        $(element).parent().parent().remove();
                    });
                    $('#validation-box').modal('hide');
                    $('#validation-box-body').empty();
                    $('.delete-groups-button').attr('disabled', 'disabled');
                    $('#deleting').hide();
                },
                type: 'DELETE'
            });
        });

        $('#modal-cancel-button').click(function () {
            $('#validation-box').modal('hide');
            $('#validation-box-body').empty();
        });

        $('.search-group-button').click(function () {
            $('.checkbox-group-name').remove();
            $('#group-table-body').empty();
            stop = false;
            if (document.getElementById('search-group-txt').value !== '') {
                mode = 1;
                lazyloadGroups(searchRoute);
            } else {
                mode = 0;
                lazyloadGroups(standardRoute);
            }
        });

        $('.button-parameters-group').live('click', function () {
            var route = Routing.generate(
                'claro_workspace_tools_show_group_parameters',
                {'groupId': $(this).parent().parent().attr('data-group-id'), 'workspaceId': twigWorkspaceId}
            );

            window.location.href = route;
        });
    }

    $('.delete-groups-button').attr('disabled', 'disabled');
    $('.loading').hide();
    initEvents();
    lazyloadGroups(standardRoute);
})();
