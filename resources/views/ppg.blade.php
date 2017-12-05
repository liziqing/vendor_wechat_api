<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="leon">
    <title>ppg证书管理界面</title>
</head>
<body>
    <input id="image_url" type="text" val="" />
    <input id="desc" type="text" val="" />
    <button type="button" onclick="addCert()">添加证书</button>
    <table id="cert-list">
        <tr><th>证书图</th><th>描述</th><th>操作</th></tr>
    </table>
</body>
<script src="//code.jquery.com/jquery-2.1.3.min.js"></script>
<script type="text/javascript">
    $(function () {
//        $('button').bind('click', addCert);
        $.ajax({
            type: "get",
            dataType: "json",
            url: "/ppg/all-certificate",
            success: function (data) {
                var list = data.data.list;
                for (var i in list) {
                    $('#cert-list').append('<tr><td><img src="'+list[i]['image_url']+'" height="150"/></td>' +
                        '<td>'+list[i]['desc']+'</td><td><a href="javascript:void(0);" onclick="delCert('+list[i]['id']+')">删除</a></td></tr>');
                }
            }
        });
    });
    function addCert() {
        $.ajax({
            type: "post",
            data: {image_url: $('#image_url').val(), desc: $('#desc').val()},
            dataType: "json",
            url: "/ppg/update-cert",
            success: function (data) {
                window.location.reload();
            }
        });
    }
    function delCert(id) {
        $.ajax({
            type: "post",
            data: {id:id},
            dataType: "json",
            url: "/ppg/del-cert",
            success: function (data) {
                window.location.reload();
            }
        });
    }
</script>
</html>