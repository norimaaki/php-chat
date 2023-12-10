<?php
session_start();

$chatFile = 'chat.txt';
$usernameFile = 'usernames.txt';

// 入室ページの処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['enter'])) {
        $username = $_POST['username'];

        // 既に同じユーザー名が使用されていないかチェック
        if (!isUsernameTaken($username)) {
            $_SESSION['user'] = $username;

            // ユーザー名を改行して保存
            file_put_contents($usernameFile, htmlspecialchars("$username\n", ENT_QUOTES), FILE_APPEND | LOCK_EX);
        } else {
            echo "ユーザー名 '$username' は既に使用されています。別のユーザー名を試してください.";
        }
    } elseif (isset($_POST['exit'])) {
        if (isset($_SESSION['user'])) {
            $exitingUser = $_SESSION['user'];
            unset($_SESSION['user']);

            // クッキーからユーザー名を削除
            setcookie('username', '', time() - 3600);

            // ユーザーが退室したらユーザー名を削除
            removeUsername($exitingUser);
        }
        // セッションを破棄
        session_destroy();
    } elseif (isset($_POST['getMessages'])) {
        // Ajaxリクエストで送られてきた場合、チャットメッセージを返す
        echo nl2br(getChatMessages());
        exit();
    }
}

// メッセージ送信の処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = $_POST['message'];
    $user = $_SESSION['user'] ?? 'Guest';

    // メッセージと送信時間をフォーマット
    $formattedMessage = htmlspecialchars("$user: $message (" . date('Y-m-d H:i:s') . ")\n", ENT_QUOTES);

    file_put_contents($chatFile, $formattedMessage, FILE_APPEND | LOCK_EX);

    // Ajaxリクエストでない場合にはリダイレクト
    if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
}

$chatContent = nl2br(file_get_contents($chatFile));

// 同じユーザー名が既に存在するかどうかをチェック
function isUsernameTaken($username)
{
    $chatContent = file_get_contents('usernames.txt');
    $usernames = explode("\n", $chatContent);

    foreach ($usernames as $existingUsername) {
        $existingUsername = trim($existingUsername);

        // ユーザー名が一致した場合、重複とみなす
        if ($existingUsername === $username) {
            return true;
        }
    }

    return false;
}

// ユーザー名を削除する関数
function removeUsername($username)
{
    $usernames = file('usernames.txt', FILE_IGNORE_NEW_LINES);

    // ユーザー名を削除
    $usernames = array_diff($usernames, array($username));

    // ユーザー名を改行して保存
    file_put_contents('usernames.txt', implode("\n", $usernames));
}

// チャットメッセージを取得する関数
function getChatMessages()
{
    global $chatFile;
    return nl2br(file_get_contents($chatFile));
}
?>


<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chatroom</title>
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <link rel="stylesheet" href="style.css">
  <script>
      $(document).ready(function () {
          var isButtonDisabled = false;

          getMessages();

          function getMessages() {
              $.ajax({
                  url: '<?php echo $_SERVER['PHP_SELF']; ?>',
                  type: 'POST',
                  data: { getMessages: true },
                  success: function (response) {
                      $('#chat').html(response);
                  }
              });
          }

          setInterval(getMessages, 5000);

          $('#chatForm').submit(function (e) {
              e.preventDefault();

              if (!isButtonDisabled) {
                  isButtonDisabled = true;

                  $.ajax({
                      url: $(this).attr('action'),
                      type: $(this).attr('method'),
                      data: $(this).serialize(),
                      success: function () {
                          $('#message').val('');
                          getMessages();
                      },
                      complete: function () {
                          setTimeout(function () {
                              isButtonDisabled = false;
                          }, 5000); 
                      }
                  });
              }
          });
      });
  </script>
</head>
<body>
    <?php if (!isset($_SESSION['user'])) : ?>
        <h1>入室ページ</h1>
        <form method="post">
            <label for="username">ユーザー名:</label>
            <input type="text" id="username" name="username" required>
            <button type="submit" name="enter">入室</button>
        </form>
    <?php else : ?>
        <h1>Chatroom</h1>

        <div id="chat">
            <strong>チャット履歴:</strong>
            <?php echo $chatContent; ?>
        </div>

        <form id="chatForm" method="post">
            <label for="message">メッセージ:</label>
            <input type="text" id="message" name="message" required>
            <button type="submit">送信</button>
        </form>

        <form method="post">
            <button type="submit" name="exit">退室</button>
        </form>
    <?php endif; ?>
</body>
</html>
