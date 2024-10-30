layui.use(['layer', 'form'], function() {
    var layer = layui.layer
        , form = layui.form
        , $ = layui.jquery;

    $("#hornbills-myi-csv-btn").click(function () {
        $.post(my_ajax_obj.ajax_url, {     //POST request
            _ajax_nonce: my_ajax_obj.nonce, //nonce
            action: "export_csv",        //action
        }, function(data) {                //callback
            if (data.success == true){
                console.log(data)  ;
                var result="\ufeff"+data.data;
                var blob = new Blob([result], {type: 'application/vnd.ms-excel'});
                var downloadUrl = URL.createObjectURL(blob);
                var a = document.createElement("a");
                a.href = downloadUrl;
                a.download = "emals.csv";
                document.body.appendChild(a);
                a.click();
            }else {
                layer.msg(data.data);
            }
        });
    });
    var active = {
        confirmTrans: function (othis,btn) {
            layer.open({
                type:1,
                area:['300px','200px'],
                title: othis.attr('title'),
                content: $("#hornbills-myi-subscribe-info-form"),
                shade: 0,
                btn: [btn],
                btn1: function(index, layero){
                    var email=$("#hornbills-myi-subscribe-email").val();
                    $.post(my_ajax_obj.ajax_url, {     //POST request
                            _ajax_nonce: my_ajax_obj.nonce, //nonce
                            action: "email_save",        //action
                            email: email              //data
                        }, function(data) {                //callback
                            if (data.success == true){
                                layer.closeAll();
                                layer.msg(data.data.msg);
                            }else {
                                layer.msg(data.data.msg);
                            }
                        });
                },
                cancel: function(layero,index){
                    layer.closeAll();
                }

            });
        }
    };

    $('.hornbills-myi-subscribe-btn').on('click', function () {
        var othis  = $(this);
        var method = othis.data('method');
        var btn    = othis.data('btn');
        active[method] ? active[method].call(this, othis,btn) : '';
    });

});