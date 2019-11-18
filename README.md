# The tiny library to send logs over UDP


## Why

I has a need of something to debug the legacy projects with a quite unreadable code and complex data sructures. The idea is it should be very lightweight and have near zero impact on debugged application.


## How it works

Is sends the data over UDP protocol to recever: <https://github.com/webzak/uzlog>.

The data are sent over UDP without awaiting for any confirmations from the receiver part. It works good on local machine and docker/local networks. I would not recommend to use it with open networks because the information is not encrypted.


## Usage

```php
use Webzak\Uzlog\{Socket, Transport, Log, Saver};

$socket = new Socket('127.0.0.1', 7000);
$transport = new Transport($socket);
$log = new Log($transport);
$saver = new Saver($transport);

$log->send("Hello");
$saver->send('servinfo.json', $_SERVER);
```

For practical use it is easier to create a global function somewhere in the common init:

```php
use Webzak\Uzlog;

#define UZL_HOST 127.0.0.1
#define UZL_PORT 7000
#define UZL_MAX_MSG_LEN 5000

function ulog($msg, array $opts = [])
{
    static $client;

    if (!is_null($client)) {
        return $client->send($msg, $opts);
    } elseif ($msg instanceof \Udplog\Log) {
        $client = $msg;
    }
}

// init the instance
ulog(new Uzlog\Log(new Uzlog\Transport(new Uzlog\Socket(UZL_HOST, UZL_PORT), ['limit' => UZL_MAX_MSG_LEN]));

// then just use it anywhere
ulog("Hello!", ['fg' => 21, 'bg' => 46]);
```


## Init parameters


### Socket

For socket the ip and port parameter must be set:

```php
$socket = new Uzlog\Socket('172.17.0.1', 7777);
```


### Transport

-   **max\_packet** - can be set for value between 21 and 508. By default it is set to 508 bytes.

```php
$transport = new Uzlog\Transport($socket, ['max_packet' => 200]);
```


### Log

-   **limit** (int) - limits the maximum size of single log message. If the message is longer than the limit, it's tail is cut and it it indicated like [10228->5000..]. This notation means that full message length was 10228 and it was cut to 5000. The option has internal default value = 500.

-   **context** (int) - if value is grater than 0, the calling context is added in front of log message. The greater the value the more deeper callstack is shown. By default it is disabled. Note that this value can be set personally per message, so may be applied only when you are really interested in calling stack investigation.

-   **context\_files** (bool) - if true it additionally shows the filenames for callstack. By default it is disabled.

```php
$log = new Uzlog\Log($transport, ['limit' => 2000, 'context' => 10]);
```


### Saver

The saver has no init options.

```php
$saver = new Uzlog\Saver($transport);
```


## Logging options

-   **fg** (0-255) - message foreground color. It is sent as a one byte value with a message. See [ANSI escape codes](https://en.wikipedia.org/wiki/ANSI_escape_code#8-bit)

-   **bg** (0-255) - message background color. It is sent as one byte value with a message.

-   **limit** (int) - overrides the global limit parameter for concrete message.

-   **transform** (string) - currently supports only **'json'** value. If message is array, it is transformed to json string. (Else by default arrays are transformed with print\_r($a,1))

-   **context** - overrided the global context parameter for concrete message.

```php
$log->send($msg1);
$log->send($msg2, ['fg' => 21, 'bg' => 46]);
$log->send($msg3, ['limit' => 5000, 'context' => true]);
$log->send($arr, ['transform' => 'json']);
```


## Saving options

-   ****append**** - if true, the data is appended to file, by default it is overwritten.
-   ****raw**** - do not prettify json, when saving arrays (arrays are automatically converted to json).

```php
$saver->send('file.txt', 'somestring');
$saver->send('data.json', $array, ['raw' => true]);
$saver->send('data.csv',  $row, ['append' => true]);
```


### Incremental filenames

The saver supports the special naming mode to generate the incremental filnames. This may be necessary when you are saving some data in loop. (The amount of '?' symbols determines the amount of digits).

```php
foreach($x as $data) {
    $saver->send('data.???.json', $data);
}
```

The results with be saved in files:

-   data.000.json
-   data.001.json

-   data.nnn.json
