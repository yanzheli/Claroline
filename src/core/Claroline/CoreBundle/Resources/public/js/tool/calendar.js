(function () {
    'use strict';

    window.Claroline = window.Claroline || {};
    var calendar = window.Claroline.Calendar = {};

    calendar.initialize = function (context) {
        context = context || 'desktop';
        var clickedDate = null,
            id = null,
            url = null;

        $('.filter').click(function (e) {
            $('#calendar').fullCalendar('removeEvents', function (eventObject) {
                var reg = new RegExp('[:]+', 'g');
                var title = eventObject.title.split(reg);
                console.debug(title);
                if (title[0] !== $(e.target).attr('name'))
                {
                    return true;
                }
                else
                {
                    return false;
                }
            });
        });

        var dayClickWorkspace = function (date) {
            clickedDate = date;
            $('#deleteBtn').hide();
            $('#save').show();
            $('#updateBtn').hide();
            $('#calendar_form').find('input:text, input:password, input:file, select, textarea').val('');
            $('#calendar_form').find('input:radio, input:checkbox')
                .removeAttr('checked')
                .removeAttr('selected');
            var  currentDate = new Date();
            var pickedDate = new Date(date);
            $('#calendar_form_start').val(date.getDate() + '/' +
                (date.getMonth() + 1) + '/' + date.getFullYear());
            if (pickedDate > currentDate) {
                $('#calendar_form_end').val(pickedDate.getDate() + '/' +
                    (pickedDate.getMonth() + 1) + '/' + pickedDate.getFullYear());
            } else {
                $('#calendar_form_end').val(currentDate.getDate() + '/' +
                    (currentDate.getMonth() + 1) + '/' + currentDate.getFullYear());
            }
            $('#myModal').modal();
        };
        var dayClickDesktop = function () {
            alert('Not implemented yet');
        };
        var dayClickFunction = context === 'desktop' ? dayClickDesktop : dayClickWorkspace;

        $('#save').click(function () {
            if ($('#calendar_form_title').val() !== '') {
                $('#save').attr('disabled', 'disabled');
                var data = new FormData($('#myForm')[0]);
                data.append('date', new Date(clickedDate));
                var url = $('#myForm').attr('action');
                $.ajax({
                    'url': url,
                    'type': 'POST',
                    'data': data,
                    'processData': false,
                    'contentType': false,
                    'success': function (data, textStatus, xhr) {
                        if (xhr.status === 200) {
                            $('#myModal').modal('hide');
                            $('#save').removeAttr('disabled');
                            if (data.allDay === false)
                            {
                                $('#calendar').fullCalendar('renderEvent',
                                {
                                    title: data.title,
                                    start: data.start,
                                    end: data.end,
                                    allDay: data.allDay,
                                    color: data.color
                                },
                                true // make the event 'stick'
                                );
                                $('#calendar').fullCalendar('unselect');
                            }
                        }
                    },
                    'error': function (xhr, textStatus) {
                        if (xhr.status === 400) {//bad request
                            alert(' Start date is bigger thand end date');
                            $('#save').removeAttr('disabled');
                            $('#output').html(textStatus);
                        } else {
                            //if we got to this point we know that the controller
                            //did not return a json_encoded array. We can assume that
                            //an unexpected PHP error occured
                            alert('An unexpeded error occured.');
                            $('#save').removeAttr('disabled');
                            //if you want to print the error:
                            $('#output').html(data);
                        }
                    }
                });
            } else {
                alert('title can not be empty');
            }
        });

        $('#updateBtn').click(function () {
            $('#updateBtn').attr('disabled', 'disabled');
            var data = new FormData($('#myForm')[0]);
            data.append('id', id);
            url = $('a#update').attr('href');
            $.ajax({
                'url': url,
                'type': 'POST',
                'data': data,
                'processData': false,
                'contentType': false,
                'success': function (data, textStatus, xhr) {
                    console.debug(xhr);
                    if (xhr.status === 200)  {
                        $('#myModal').modal('hide');
                        $('#updateBtn').removeAttr('disabled');
                        $('#calendar').fullCalendar('refetchEvents');
                    }
                }
            });
        });

        var deleteClick = function (id) {
            $('#deleteBtn').attr('disabled', 'disabled');
            url = $('a#delete').attr('href');
            $.ajax({
                'type': 'POST',
                'url': url,
                'data': {
                    'id': id
                },
                'success': function (data, textStatus, xhr) {
                    if (xhr.status === 200) {
                        $('#myModal').modal('hide');
                        $('#deleteBtn').removeAttr('disabled');
                        $('#calendar').fullCalendar('removeEvents', id);
                    }
                }
            });
        };

        /*
        * function to delete a task
        currentTarget = the object clicked
        */
        $('.delete-task').on('click', function (e) {
            var id = $(e.currentTarget).attr('data-event-id');
            deleteClick(id);
        });

        $('.update-task').on('click', function (e) {
            $('#save').hide();
            var list = e.target.parentElement.children;
            $('#myModal').modal('show');
            $('#calendar_form').find('input:text, input:password, input:file, select, textarea').val('');
            $('#myModalLabel').val('Modifier une entrée');
            $('#calendar_form_title')
                .attr('value', $(e.target.parentElement.parentElement.children)[1].innerHTML);
            $('#calendar_form_start').val($(list[0])[0].innerHTML);
            $('#calendar_form_end').val($(list[1])[0].innerHTML);
            $('#calendar_form_description').val($(list[2])[0].innerHTML);
        });
        function dropEvent(event, dayDelta, minuteDelta) {
            id = event.id;
            $.ajax({
                'url': $('a#move').attr('href'),
                'type': 'POST',
                'data': {
                    'id': event.id,
                    'dayDelta': dayDelta,
                    'minuteDelta': minuteDelta
                },
                'success': function (data, textStatus, xhr) {
                    //the response is in the data variable

                    if (xhr.status  === 200) {
                        alert('event update');
                    }
                    else if (xhr.status === 500) {//internal server error
                        alert('An error occured' + data.greeting);
                        $('#output').html(data);
                    }
                    else {
                        //if we got to this point we know that the controller
                        //did not return a json_encoded array. We can assume that
                        //an unexpected PHP error occured
                        alert('An unexpeded error occured.');

                        //if you want to print the error:
                        $('#output').html(data);
                    }
                }
            });

            if (!confirm('Are you sure about this change?')) {
                //@todo revertFunc ? what is this ?
                //revertFunc(); ? what is this ?
                alert('Please tell me what I\'m supposed to do');
            }
        }
        function modifiedEvent(calEvent)
        {
            id = calEvent.id;
            $('#deleteBtn').show();
            $('#updateBtn').show();
            $('#save').hide();
            $('#myModalLabel').val('Modifier une entrée');
            $('#calendar_form_title').attr('value', calEvent.title);
            $('#calendar_form_description').val(calEvent.description);
            $('#calendar_form_priority option[value=' + calEvent.color + ']').attr('selected', 'selected');
            var pickedDate = new Date(calEvent.start);
            $('#calendar_form_start').val(pickedDate.getDate() + '/' +
                (pickedDate.getMonth() + 1) + '/' + pickedDate.getFullYear());
            if (calEvent.end === null)
            {

                $('#calendar_form_end').val(pickedDate.getDate() + '/' +
                (pickedDate.getMonth() + 1) + '/' + pickedDate.getFullYear());
            }
            else
            {
                var Enddate = new Date(calEvent.end);
                $('#calendar_form_end').val(Enddate.getDate() + '/' +
                (Enddate.getMonth() + 1) + '/' + Enddate.getFullYear());
            }

            $.ajaxSetup({
                'type': 'POST',
                'error': function (xhr, textStatus) {
                    if (xhr.status === 500) {//bad request
                        alert('An error occured' + textStatus);
                    }
                }
            });

            $('#myModal').modal();
        }
        $('#deleteBtn').on('click', function () {
            deleteClick(id);
        });

        $('#calendar').fullCalendar({
            header: {
                left: 'prev,next today',
                center: 'title',
                right: 'month,basicWeek,basicDay'
            },
            monthNames: ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet',
                'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'],
            monthNamesShort: ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août',
                'sept.', 'oct.', 'nov.', 'déc.'],
            dayNames: ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'],
            dayNamesShort: ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'],
            editable: true,
            events: $('a#link').attr('href'),
            timeFormat: 'H(:mm)',
            agenda: 'h:mm{ - h:mm}',
            allDayText: 'all-day',
            allDaySlot: true,
            eventDrop: function (event, dayDelta, minuteDelta) {
                dropEvent(event, dayDelta, minuteDelta);
            },
            dayClick: dayClickFunction,
            eventClick:  function (calEvent) {
                modifiedEvent(calEvent);
            },
            eventRender: function () {

            },
            eventResize: function (event, dayDelta, minuteDelta, revertFunc) {
                $.ajax({
                    'url': $('a#move').attr('href'),
                    'type': 'POST',
                    'data' : {
                        'id': event.id,
                        'dayDelta': dayDelta,
                        'minuteDelta': minuteDelta
                    },
                    'success': function (data, textStatus, xhr) {
                        //the response is in the data variable

                        if (xhr.status === 200) {
                            alert('event update');
                        }
                        else {
                            //if we got to this point we know that the controller
                            //did not return a json_encoded array. We can assume that
                            //an unexpected PHP error occured
                            alert('An unexpeded error occured.');

                            //if you want to print the error:
                            $('#output').html(data);
                        }
                    }
                });
                if (!confirm('is this okay?')) {
                    revertFunc();
                }
            }
        });
    };
}) ();