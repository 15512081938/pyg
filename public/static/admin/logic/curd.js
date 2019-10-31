$(function(){
    $('#add').click(function(){
        var type = $(this).attr('data-type');
        var title = '添加';
        var url = $(this).attr('data-url');
        if(type == 'full'){
            var index = layer.open({
                type: 2,
                title: title,
                content: url
            });
            layer.full(index);
        }else{
            var w = '';
            var h = '360';
            layer_show(title,url,w,h);
        }
    });
    $('.add_child').click(function(){
        var title = '添加下级';
        var url = $(this).attr('data-url');
        var w = '';
        var h = '360';
        layer_show(title,url,w,h);
    });
    $('.edit').click(function(){
        var type = $(this).attr('data-type');
        var title = '修改';
        var url = $(this).attr('data-url');
        if(type == 'full'){
            var index = layer.open({
                type: 2,
                title: title,
                content: url
            });
            layer.full(index);
        }else{
            var w = '';
            var h = '360';
            layer_show(title,url,w,h);
        }
    });
    $('.delete').click(function(){
        var obj = this;
        layer.confirm('删除须谨慎，确认要删除吗？',function(index){
            var url = $(obj).attr('data-url');
            $.ajax({
                "url":url,
                "type":'post',
                "dataType":"json",
                "success":function(res){
                    if(res.code == 200){
                        $(obj).closest("tr").remove();
                        layer.msg('已删除!',{icon:1,time:1000});
                    }else{
                        layer.msg('删除失败：' + res.msg);
                    }
                }
            });


        });
    });
    $('#patch_delete').click(function(){
        var obj = this;
        layer.confirm('删除须谨慎，确认要删除吗？',function(index){
            var url = $(obj).attr('data-url');
            var ids = [];
            $('.row_check:checked').each(function(i,v){
                ids.push($(v).val());
            });
            var data = {"id":ids.join(',')};
            $.ajax({
                "url":url,
                "type":'post',
                "data":data,
                "dataType":"json",
                "success":function(res){
                    if(res.code == 200){
                        $('.row_check:checked').closest('tr').remove();
                        layer.msg('已删除!',{icon:1,time:1000});
                    }else{
                        layer.msg('删除失败：' + res.msg);
                    }
                }
            });


        });
    });
});
