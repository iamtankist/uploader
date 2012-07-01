$(document).ready(function(){

    function renderFileListTable(files){
        var $table = $('<table></table>').addClass('table-striped');

        $.each(files,function(index,file){
            var $row = $('<tr></tr>').addClass('record');//.addClass(index%2?'even':'odd');
            var $indexCell = $('<td></td>').addClass('index').html(index+1);
            var $fileCell = $('<td></td>').addClass('filename').html(file.name);
            var $statusCell = $('<td></td>').addClass('status').html(file.vimeo.status);
            var $vimeoCell = $('<td></td>').addClass('vimeo').html('');
            var $actionCell = $('<td></td>').addClass('actions');

            var $playAction = $('<a href="javascript:void(0);"></a>').addClass('icon-play-circle').addClass('play');
                $vimeoCell.append($playAction);
            var $uploadAction = $('<a href="javascript:void(0);"></a>').addClass('icon-upload').addClass('upload');
                $vimeoCell.append($uploadAction);

            if(file.vimeo.id) {
                $playAction.css({display:'block'});
                $uploadAction.css({display:'none'});
            } else {
                $playAction.css({display:'none'});
                $uploadAction.css({display:'block'});
            }

            var $infoAction = $('<a href="javascript:void(0);"></a>').addClass('icon-info-sign').addClass('info');



            $actionCell.append($infoAction);

            $row.append($indexCell,$fileCell,$statusCell,$vimeoCell,$actionCell);
            $table.append($row);

            activateListButtons();
        });

        $('#fileList').append($table);
        activateListButtons();
    }

    function activateListButtons(){
        $('.upload').unbind().bind('click',function(){
            var $record = $(this).closest('.record');
            var filename = $record.find('.filename').html();
                $.ajax({
                    url: jsPath.upload,
                    data: {filename:filename},
                    dataType: 'json',
                    success: function(data) {
                        console.log(data);
                        
                        var color;
                        if(data.status == 'error') {
                            color = 'red';
                        } else {
                            color = 'green';
                            //$record.find('.upload').hide();
                            //$record.find('.play').show();
                        }
                        $record.find('td').effect("highlight", {color:color}, 1000);
                        /*var files = data.files;
                        renderFileListTable(files);*/
                    }
                });
        });

        $('.info').unbind().bind('click',function(){
            var $record = $(this).closest('.record');
            var filename = $record.find('.filename').html();
                $.ajax({
                    url: jsPath.info,
                    data: {filename:filename},
                    dataType: 'json',
                    success: function(data) {
                        console.log(data);
                        
                        var color;
                        if(data.status == 'error') {
                            color = 'red';
                        } else {
                            color = 'green';
                            $record.find('.upload').hide();
                            $record.find('.play').show();
                            $record.find('.status').html(data.status);
                        }
                        $record.find('td').effect("highlight", {color:color}, 1000);
                        /*var files = data.files;
                        renderFileListTable(files);*/
                    }
                });


            
        });

        $('.play').unbind().bind('click',function(){
            alert('play'+$(this).closest('.record').find('.filename').html());
        });
    }



    function requestFileList(){
        $.ajax({
            url: jsPath.list,
            dataType: 'json',
            success: function(data) {
                var files = data.files;
                renderFileListTable(files);
            }
        });
    }

    requestFileList();


 });