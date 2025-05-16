Subject: 论坛新主题：{!forumName!}
Content-Type: text/html; charset=UTF-8

<html lang="zh" dir="ltr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{topicSubject}}</title>
</head>
<body>
  <p>'{{poster}}' 在您订阅的论坛 '{{forumName}}' 中发布了新主题 '<a href="{!topicLink!}">{{topicSubject}}</a>'。</p>
  <p></p>
  <p>主题内容如下：</p>
  <p>-----------------------------------------------------------------------</p>
  <p></p>
  <div class="f-post-body">
    <div class="f-post-main">
      <p>{!message!}</p>
    </div>
  </div>
  <p></p>
  <p>-----------------------------------------------------------------------</p>
  <p></p>
  <p>您可以通过访问<a href="{!unsubscribeLink!}">此链接</a>并点击页面底部的{!button!}按钮来取消订阅。</p>
  <p></p>
  <p>--</p>
  <p>{{fMailer}} 邮件系统</p>
  <p>（请勿回复此消息）</p>
</body>
</html>
