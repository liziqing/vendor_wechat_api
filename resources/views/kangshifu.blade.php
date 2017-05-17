<h1>康师傅活动审核</h1>
<p>
    <a href="javascript:void(0);" onclick="showlist('2')">照片墙</a>
    <a href="javascript:void(0);" onclick="showlist('3')">未上墙</a>
</p>
<p>
    <a href="javascript:void(0);" onclick="showlist('4')">待审核</a>
    <a href="javascript:void(0);" onclick="showlist('5')">已通过</a>
    <a href="javascript:void(0);" onclick="showlist('6')">不通过</a>
</p>
<p>
    <a href="javascript:void(0);" onclick="showLottery()">中奖列表</a>
</p>

<div id="image_list"></div>

<script src="//code.jquery.com/jquery-2.1.3.min.js"></script>
<script type="text/javascript">
    $(document).ready(function(){
        showlist(4);
    });
    function showlist(type) {
        $("#image_list").html("");
        $.ajax({
            type:"get",
            data:{type:type,admin:1},
            dataType:"json",
            url:"/kangshifu/image-list",
            success:function (data) {
                var oneLen = 5;
                var cyc = 0;
                var coverLine = '';
                var titleLine = '';
                var list = data.data.list;
                for (var mobile in list)
                {
                    var oneData = list[mobile];
                    for (var i in oneData)
                    {
                        coverLine += "<td><img src='"+oneData[i].url+"' title='"+mobile+"' height='150'/></td>";
                        titleLine += "<td>" + mobile + "  " + oneData[i].time;
                        if (5 != type)
                        {
                            titleLine += "<a href='javascript:void(0);' onclick=\"chgStatus('"+oneData[i].url+"', '"+mobile+"', "+type+", 1)\">通过</a>--" +
                                "<a href='javascript:void(0);' onclick=\"chgStatus('"+oneData[i].url+"', '"+mobile+"', "+type+", 2)\">不通过</a>";
                        }
//                        coverLine += "</td>";
                        titleLine += "</td>";

                        if (oneLen <= cyc++)
                        {
                            $("#image_list").append("<tr>"+coverLine+"</tr> <tr>"+titleLine+"</tr>");
                            cyc = 0;
                            coverLine = '';
                            titleLine = '';
                        }
                    }
                }
                if (0 !== coverLine.length) {
                    $("#image_list").append("<tr>"+coverLine+"</tr> <tr>"+titleLine+"</tr>");
                }
            }
        });
    }
    function chgStatus(url, mobile, type, result) {
        var map = {2:22, 3:21, 4:11, 5:12, 6:13};
        var mapType = map[type];
        $.ajax({
            type:"post",
            data:{url:url,mobile:mobile,result:result,type:mapType},
            dataType:"json",
            url:"/kangshifu/change-status",
            success:function (data) {
                showlist(type);
            }
        });
    }
    function showLottery() {
        $("#image_list").html("");
        $.ajax({
            type:"get",
            data:{},
            dataType:"json",
            url:"/kangshifu/lottery-result",
            success:function (data) {
                var oneLen = 5;
                var cyc = 0;
                var coverLine = '';
                var list = data.data.list;
                for (var i in list)
                {
                    coverLine = '';
                    coverLine += "<td>"+list[i]['mobile']+" || </td>";
                    coverLine += "<td>"+list[i]['name']+" || </td>";
                    coverLine += "<td>";
                    var oneData = list[i]['prize'];
                    for (var j in oneData)
                    {
                        coverLine += oneData[j]+" , ";
                    }
                    coverLine += "</td>";
                    $("#image_list").append("<tr>"+coverLine+"</tr>");
                }
            }
        });
    }
</script>
