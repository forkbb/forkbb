Subject: New topic in forum: {!forumName!}
Content-Type: text/html; charset=UTF-8

<html lang="fr" dir="ltr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{topicSubject}}</title>
</head>
<body>
  <p>'{{poster}}' a lancé une nouvelle discussion  '<a href="{!topicLink!}">{{topicSubject}}</a>' dans le forum '{{forumName}}', que vous suivez.</p>
  <p></p>
  <p>Contenu du message:</p>
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
  <p>Vous pouvez interrompre le suivi en cliquant ici :  <a href="{!unsubscribeLink!}">link</a> et cliquer le {!button!} bouton en bas de page.</p>
  <p></p>
  <p>--</p>
  <p>{{fMailer}} Mailer</p>
  <p>(Ne pas répondre à ce message)</p>
</body>
</html>
