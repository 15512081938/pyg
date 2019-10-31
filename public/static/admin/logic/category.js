$(function(){
    //商品分类三级联动
    $('#cate_one').change(function(){
        var pid = $(this).val();
        $.ajax({
            'url':$('#cate_one').attr('data-url'),
            'type':'post',
            'data':{'pid':pid},
            'dataType':'json',
            'success':function(response){
                if(response.code != 10000){
                    alert(response.msg);return;
                }
                var str = '<option value="">请选择二级分类</option>';
                $.each(response.data, function(i,v){
                    str += '<option value="' + v.id + '">' + v.cate_name + '</option>';
                });
                $('#cate_two').html(str);
            }
        });
    });
    $('#cate_two').change(function(){
        var pid = $(this).val();
        $.ajax({
            'url':$('#cate_one').attr('data-url'),
            'type':'post',
            'data':{'pid':pid},
            'dataType':'json',
            'success':function(response){
                if(response.code != 10000){
                    alert(response.msg);return;
                }
                var str = '<option value="">请选择二级分类</option>';
                $.each(response.data, function(i,v){
                    str += '<option value="' + v.id + '">' + v.cate_name + '</option>';
                });
                $('#cate_three').html(str);
            }
        });
    });
});
