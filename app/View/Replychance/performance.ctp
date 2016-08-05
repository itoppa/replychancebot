<ul class="list-unstyled">
<?php foreach ($twitterAccounts as $twitterAccount) : ?>
  <li>
    <?php echo $twitterAccount['TwitterAccount']['screen_name']; ?>&nbsp;
    <a href="https://twitter.com/<?php echo $twitterAccount['TwitterAccount']['screen_name']; ?>" target="_blank">Twitter</a>&nbsp;&nbsp;
    performance&nbsp;&nbsp;
    <a href="/app2/replychance/execute?screen_name=<?php echo $twitterAccount['TwitterAccount']['screen_name']; ?>">execute</a>
  </li>
  <table class="table">
    <thead>
      <tr>
        <th>hour</th>
        <th>receive</th>
        <th>send</th>
        <th>percent</th>
      </tr>
    </thead>
    <tbody>
  <?php for ($i=0; $i<24; $i++) : ?>
    <?php
    $hour = sprintf('%02d', $i);
    $receiveTweet = $receiveTweets[$twitterAccount['TwitterAccount']['id']];
    $receive = (isset($receiveTweet[$hour])) ? $receiveTweet[$hour] : 0;
    $sendTweet = $sendTweets[$twitterAccount['TwitterAccount']['id']];
    $send = (isset($sendTweet[$hour])) ? $sendTweet[$hour] : 0;
    $percent = (isset($percents[$twitterAccount['TwitterAccount']['id']][$hour])) ? $percents[$twitterAccount['TwitterAccount']['id']][$hour] : '-';
    ?>
      <tr>
        <th scope="row"><?php echo $hour; ?></th>
        <td><?php echo $receive; ?></td>
        <td><?php echo $send; ?></td>
        <td><?php echo $percent; ?></td>
      </tr>
  <?php endfor; ?>
    </tbody>
  </table>
<?php endforeach; ?>
</ul>
