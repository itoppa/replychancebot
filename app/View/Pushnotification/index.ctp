<ul class="list-unstyled">
<?php foreach ($pushNotifications as $pushNotification) : ?>
  <li>created:<?php echo ($pushNotification['PushNotification']['created']); ?>, body:<?php echo ($pushNotification['PushNotification']['body']); ?></li>
<?php endforeach; ?>
</ul>
