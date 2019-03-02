<?php
    if(isset($_POST['message']))
    {
        file_put_contents('hash.txt', $_POST['message']);
        echo 'Сообщение сохранено. Обновите страницу';
        die;
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Зашифрованная переписка</title>
    <script src="jquery.js"></script>
    <script src="openpgp.min.js"></script>
    <style>
        .block {
            border: 1px solid;
            margin: 5px;
            padding: 5px;
            word-break: break-all;
        }
    </style>
    <script>
        function Utf8ArrayToStr(array) {
            var out, i, len, c;
            var char2, char3;

            out = "";
            len = array.length;
            i = 0;
            while(i < len) {
                c = array[i++];
                switch(c >> 4)
                {
                    case 0: case 1: case 2: case 3: case 4: case 5: case 6: case 7:
                    // 0xxxxxxx
                    out += String.fromCharCode(c);
                    break;
                    case 12: case 13:
                    // 110x xxxx   10xx xxxx
                    char2 = array[i++];
                    out += String.fromCharCode(((c & 0x1F) << 6) | (char2 & 0x3F));
                    break;
                    case 14:
                        // 1110 xxxx  10xx xxxx  10xx xxxx
                        char2 = array[i++];
                        char3 = array[i++];
                        out += String.fromCharCode(((c & 0x0F) << 12) |
                            ((char2 & 0x3F) << 6) |
                            ((char3 & 0x3F) << 0));
                        break;
                }
            }

            return out;
        }

        window.last_messages = JSON.parse('<?=file_get_contents('hash.txt');?>');

        async function _decrypt_me()
        {
            alert('Длина шифра: ' + window.last_messages.length + ', для расшифровки нужен пароль');

            toDecry = new Uint8Array(Object.values(window.last_messages));

            passwd = prompt('Каким паролем зашифровано последнее сообщение? Без него никак не узнать, что там');

            if(!passwd)
                return;

            options = {
                message: await openpgp.message.read(toDecry), // parse encrypted bytes
                passwords: [passwd],              // decrypt with password
                format: 'binary'                          // output as Uint8Array
            };

            openpgp.decrypt(options).then(function(plaintext) {
                plain = plaintext.data;
                alert('Результат расшифровки: ' + Utf8ArrayToStr(plain));
                return plaintext.data // Uint8Array([0x01, 0x01, 0x01])
            }).catch(function(e) {
                alert('Ошибка: ' + e);
            });
        }

        function _encrypt_me()
        {
            str = $('#message').val();

            if(!confirm("Будет зашифровано сообщение: " + str))
                return;

            passwd = prompt('Придумай пароль. Им мы всё зашифруем, он же потом нужен для расшифровки');

            if(!passwd)
                return;

            var options, encrypted;

            options = {
                message: openpgp.message.fromText(str), // input as Message object
                passwords: [passwd],                                             // multiple passwords possible
                armor: false,                                                             // don't ASCII armor (for Uint8Array output)
                compression: openpgp.enums.compression.zip
            };

            openpgp.encrypt(options).then(function(ciphertext) {
                encrypted = ciphertext.message.packets.write(); // get raw encrypted packets as Uint8Array

                $.post('', {'message': JSON.stringify(encrypted)}, function(ans) {
                    alert(ans);
                });
            });
        }
    </script>
</head>
<body>
    <div class="block">
        <img alt="Kitty" src="kitty.jpeg" align="right" />
        <h2>Привет! Я Китти 🐈</h2>
        <p>Это моя временная самописная страничка</p>
        <p>Я Китти, и моя работа - шифровать переписки</p>
        <p>Пока что работаю очень просто: ты пишешь сообщение, оно шифруется на стороне твоего браузера и сохраняется в файл</p>
        <p>Сохранить можно лишь одно сообщение, новое перезатирает старое</p>
        <p>У тебя есть только ключ для зашифровки, а у собеседника - для расшифровки. Таким образом можешь ему здесь оставить сообщение</p>
    </div>

    <div class="block">
        <p>Напиши сообщение и нажми на отправку 😎</p>
        <p>
            <textarea style="width:100%;height:250px;" id="message" placeholder="Раз два три четыре пять, я киса"></textarea>
        </p>
        <input type="button" onclick="_encrypt_me()" value="Сохранить">
    </div>

    <div class="block">
        Шифр последнего сообщения (для расшифровки нужен ключ)
        <div class="block">
            <?=file_get_contents('hash.txt');?>
        </div>

        <input type="button" onclick="_decrypt_me()" value="Расшифровать" />
    </div>
</body>
</html>