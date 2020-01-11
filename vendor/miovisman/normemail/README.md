# NormEmail

[![MIT licensed](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

## Installation
```
composer require miovisman/normemail
```

## normalize() method
``` php
$nEmail = new MioVisman\NormEmail\NormEmail();

$email = $nEmail->normalize($email);
```

* ### Method does not validate email address
* ### Do not use normalized email to send emails
* Use a normalized email to check the ban or uniqueness of the email of a new user. Check on the normalized emails ;)
* The domain is lowercase (and in Punycode)
* The local part is lowercase unless otherwise specified
* The local part after the "+" is truncated (for Yahoo domains - after the "-")

```
// some string
                                      =>
ExampLe                               => example
ExampLe@                              => example@
exaMple.COM                           => example.com
.example.com                          => .example.com
@examPLe.com                          => example.com
"examPLe.com                          => "example.com
"USER+++NAME@EXAMpLE.com              => "USER+++NAME@example.com
googlemail.com                        => gmail.com
pm.me                                 => protonmail.com
yandex.tj                             => yandex.ru
ya.ru                                 => yandex.ru
.ya.ru                                => .yandex.ru

// Unicode
ПОЛЬЗОВАТЕЛЬ@домен.РУ                 => пользователь@xn--d1acufc.xn--p1ag
пользователь+тег@домен.ру             => пользователь@xn--d1acufc.xn--p1ag

// Gmail
User.namE+tag@gmail.com               => username@gmail.com
u.sern.ame+tag+tag+tag@googlemail.com => username@gmail.com

// Protonmail
u_s.e-rname+tag@pm.me                 => username@protonmail.com
user-name@protonmail.ch               => username@protonmail.com

// Yahoo (.com, .ae, .at, ...)
username-tag@yahoo.com                => username@yahoo.com
user+name-tag@yahoo.fr                => user+name@yahoo.fr

// Yandex (13 domains)
user.name+tag@яндекс.рф               => user-name@yandex.ru
user-name@yandex.com                  => user-name@yandex.ru
username@ya.ru                        => username@yandex.ru
```


## License

This project is under MIT license. Please see the [license file](LICENSE) for details.
