Subject: Ответ в теме '{!topicSubject!}'
Content-Type: text/html; charset=UTF-8

<html lang="ru" dir="ltr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{topicSubject}}</title>
</head>
<body>
  <p>Пользователь '{{replier}}' ответил в теме '<a href="{!postLink!}">{{topicSubject}}</a>', на которую вы подписаны. Возможно есть и другие ответы, мы всего лишь рассылаем извещения о том, что стоит посетить форум снова.</p>
  <p></p>
  <p>Само сообщение:</p>
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
  <p>Вы можете снять подписку с темы '{{topicSubject}}' перейдя по <a href="{!unsubscribeLink!}">этой ссылке</a> и нажав кнопку '{!button!}' внизу страницы.</p>
  <p></p>
  <p>--</p>
  <p>Отправитель {{fMailer}}</p>
  <p>(Не отвечайте на это сообщение)</p>
</body>
</html>
