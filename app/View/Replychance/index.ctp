<ul class="list-unstyled">
<?php foreach ($twitterAccounts as $twitterAccount) : ?>
  <li><a href="https://twitter.com/<?php echo ($twitterAccount['TwitterAccount']['screen_name']); ?>"><?php echo ($twitterAccount['TwitterAccount']['screen_name']); ?></a></li>
  <ul class="list-unstyled">
  <?php foreach ($twitterAccount['ReplyChanceLog'] as $replyChanceLog) : ?>
    <li>created:<?php echo ($replyChanceLog['created']); ?>, count:<?php echo ($replyChanceLog['count']); ?></li>
  <?php endforeach; ?>
  </ul><br />
<?php endforeach; ?>
</ul>
