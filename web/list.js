$(document).ready(function(){

    function renderFileListTable(files){
        var $table = $('<table></table>').addClass('table-striped');

        $.each(files,function(index,file){
            var $row = $('<tr></tr>').addClass('record');//.addClass(index%2?'even':'odd');
            var $indexCell = $('<td></td>').addClass('index').html(index+1);
            var $fileCell = $('<td></td>').addClass('filename').html(file.name);
            var $vimeoCell = $('<td></td>').addClass('vimeo').html('');
            var $actionCell = $('<td></td>').addClass('actions');

            if(file.vimeo) {
                var $playAction = $('<a href="javascript:void(0);"></a>').addClass('icon-play-circle').addClass('play');
                $vimeoCell.append($playAction);
            } else {
                var $uploadAction = $('<a href="javascript:void(0);"></a>').addClass('icon-upload').addClass('upload');
                $vimeoCell.append($uploadAction);
            }

            var $infoAction = $('<a href="javascript:void(0);"></a>').addClass('icon-info-sign').addClass('info');



            $actionCell.append($infoAction);

            $row.append($indexCell,$fileCell,$vimeoCell,$actionCell);
            $table.append($row);

            activateListButtons();
        });

        $('#fileList').append($table);
        activateListButtons();
    }

    function activateListButtons(){
        $('.upload').bind('unbind').bind('click',function(){
            alert('upload'+$(this).closest('.record').find('.filename').html());
        });

        $('.info').bind('unbind').bind('click',function(){
            var filename = $(this).closest('.record').find('.filename').html();
                $.ajax({
                    url: '/app_dev.php/info/',
                    data: {filename:filename},
                    dataType: 'json',
                    success: function(data) {
                        /*var files = data.files;
                        renderFileListTable(files);*/
                    }
                });


            alert('info'+filename);
        });

        $('.play').bind('unbind').bind('click',function(){
            alert('play'+$(this).closest('.record').find('.filename').html());
        });
    }



    function requestFileList(){
        $.ajax({
            url: '/app_dev.php/list/',
            dataType: 'json',
            success: function(data) {
                var files = data.files;
                renderFileListTable(files);
            }
        });
    }

    requestFileList();


 });