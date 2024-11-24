Subject: Nuevo tema en el foro: {!forumName!}
Content-Type: text/html; charset=UTF-8

<html lang="es" dir="ltr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{topicSubject}}</title>
</head>
<body>
  <p>'{{poster}}' ha publicado un nuevo tema '<a href="{!topicLink!}">{{topicSubject}}</a>' en el foro '{{forumName}}', al que está suscrito.</p>
  <p></p>
  <p>El mensaje dice lo siguiente:</p>
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
  <p>Puedes darse de baja en <a href="{!unsubscribeLink!}">link</a> y pulsando el botón {!button!} en la parte inferior de la página.</p>
  <p></p>
  <p>--</p>
  <p>{{fMailer}} Mailer</p>
  <p>(No respondas a este mensaje)</p>
</body>
</html>
