Subject: New topic in forum: {!forumName!}
Content-Type: text/html; charset=UTF-8

<html lang="en" dir="ltr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{topicSubject}}</title>
</head>
<body>
  <p>'{{poster}}' has posted a new topic '<a href="{!topicLink!}">{{topicSubject}}</a>' in the forum '{{forumName}}', to which you are subscribed.</p>
  <p></p>
  <p>The message reads as follows:</p>
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
  <p>You can unsubscribe by going to <a href="{!unsubscribeLink!}">link</a> and clicking the {!button!} button at the bottom of the page.</p>
  <p></p>
  <p>--</p>
  <p>{{fMailer}} Mailer</p>
  <p>(Do not reply to this message)</p>
</body>
</html>
