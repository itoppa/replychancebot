<ul class="list-unstyled">
<?php foreach ($twitterAccounts as $twitterAccount) : ?>
  <li>
    <?php echo $twitterAccount['TwitterAccount']['screen_name']; ?>&nbsp;
    <a href="https://twitter.com/<?php echo $twitterAccount['TwitterAccount']['screen_name']; ?>" target="_blank">twitter</a>&nbsp;&nbsp;
    <a href="/app2/replychance/performance?screen_name=<?php echo $twitterAccount['TwitterAccount']['screen_name']; ?>">performance</a>&nbsp;&nbsp;
    <a href="/app2/replychance/execute?screen_name=<?php echo $twitterAccount['TwitterAccount']['screen_name']; ?>">execute</a>
  </li>
  <ul class="list-unstyled">
  <?php foreach ($twitterAccount['ReplyChanceLog'] as $replyChanceLog) : ?>
    <li>created:<?php echo ($replyChanceLog['created']); ?>, count:<?php echo ($replyChanceLog['count']); ?></li>
  <?php endforeach; ?>
  </ul><br />
<?php endforeach; ?>
</ul>
