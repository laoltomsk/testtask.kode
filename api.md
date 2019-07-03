### POST /api/register

Регистрация нового пользователя.

* Headers: нет.
* Request body: `object` (JSON)
    + `username`: `string` - имя пользователя
    + `password`: `string` - пароль пользователя
* OK Response: `object` (JSON)
    + `isSuccess`: `boolean` - всегда `true`
    + `token`: `string` - токен для авторизации (действителен 30 минут)
* Error responses:
    1. 409 Confilct, пользователь с таким именем уже существует: object (JSON)
       + `isSuccess`: `false`
       + `code`: `409`
       + `text`: `"This username is already in use."`


### POST /api/auth

Авторизация пользователя.

* Headers: нет.
* Request body: `object` (JSON)
    + `username`: `string` - имя пользователя
    + `password`: `string` - пароль пользователя
* OK Response: `object` (JSON)
    + `isSuccess`: `boolean` - всегда `true`
    + `token`: `string` - токен для авторизации (действителен 30 минут)
* Error responses:
    1. 404 Not Found, пользователь с такими данными не найден: object (JSON)
       + `isSuccess`: `false`
       + `code`: `404`
       + `text`: `"User with these username and password not found."`
       
### DELETE /api/auth

Logout пользователя с мгновенным прекращением действия токена.

* Headers:
    + `Authorization`: `Bearer <token>`
* Request body: нет.
* OK Response: `object` (JSON)
    + `isSuccess`: `boolean` - всегда `true`
* Error responses:
    1. 401 Unauthorized, не предоставлен токен авторизации: object (JSON)
       + `isSuccess`: `false`
       + `code`: `401`
       + `text`: `"You need to be authorized to make this request."`
    2. 403 Forbidden, токен не найден в БД либо устарел: object (JSON)
       + `isSuccess`: `false`
       + `code`: `403`
       + `text`: `"Your token is invalid."`

### GET /posts

То же, что GET /posts/pages/0.

### GET /posts/pages/{номер страницы}

Пагинированный вывод всех постов (по 10 на страницу).

* Headers: нет.
* Request body: нет.
* OK Response: `object` (JSON)
    + `isSuccess`: `boolean` - всегда `true`
    + `pageNumber`: `number` - номер запрошенной страницы
    + `posts`: `array of objects` - информация о постах
        - `id` : `number` - идентификатор
        - `username` : `string` - ник автора
        - `text` : `string` - текст *optional*
        - `likes` : `number` - число лайков
        - `time` : `number` - время создания (UNIX Timestamp)
        - `link` : `object` - информация о прикреплённой ссылке
            - `url` : `string` - адрес *optional*
            - `pic` : `string` - адрес изображения *optional*
            - `title` : `string` - заголовок страницы *optional*
* Error responses: нет.
* Изместные проблемы: `link.title` может передаваться в неверной кодировке или
    содержать данные, не являющиеся заголовком страницы. Кроме того, `link.title`
    и `link.pic` чаще, чем следует, оказываются `NULL`. 

### GET /post/{id поста}

Получение подробной информации об одном посте.

* Headers:
    + `Authorization`: `Bearer <token>` *optional*
* Request body: нет.
* OK Response: `object` (JSON)
    + `isSuccess`: `boolean` - всегда `true`
    + `id` : `number` - идентификатор
    + `username` : `string` - ник автора
    + `text` : `string` - текст *optional*
    + `likes` : `number` - число лайков
    + `time` : `number` - время создания (UNIX Timestamp)
    + `link` : `object` - информация о прикреплённой ссылке
        - `url` : `string` - адрес *optional*
        - `pic` : `string` - адрес изображения *optional*
        - `title` : `string` - заголовок страницы *optional*
    + `attachments` : `array of object` - информация о прикреплённом контенте
        - `type` : `string` - тип (`picture` или `video`)
        - `url` : `string` - URL
    + `isLikedByMe` : `boolean` - `true`, если передан токен авторизации
        и соответствующий ему пользователь лайкнул пост; в любом ином случае `false`      
* Error responses:
    1. 404 Not Found, пост не найден: object (JSON)
       + `isSuccess`: `false`
       + `code`: `404`
       + `text`: `"Post not found."`
* Изместные проблемы: `link.title` может передаваться в неверной кодировке или
    содержать данные, не являющиеся заголовком страницы. Кроме того, `link.title`
    и `link.pic` чаще, чем следует, оказываются `NULL`. 

### POST /api/posts

Добавление поста.

* Headers:
    + `Authorization`: `Bearer <token>`
* Request body: `object` (JSON)
    + `text`: `string` - текст добавляемого поста *optional*
    + `link`: `string` - ссылка, прикреплённая к посту *optional*
    + `attachments` : `array of object` - информация о прикреплённом контенте  *optional*
        - `type` : `string` - тип (`picture` или `video`)
        - `url` : `string` - URL
* OK Response: `object` (JSON)
    + `isSuccess`: `boolean` - всегда `true`
    + `postId`: `number` - идентификатор добавленного поста
* Error responses:
    1. 401 Unauthorized, не предоставлен токен авторизации: object (JSON)
       + `isSuccess`: `false`
       + `code`: `401`
       + `text`: `"You need to be authorized to make this request."`
    2. 403 Forbidden, токен не найден в БД либо устарел: object (JSON)
       + `isSuccess`: `false`
       + `code`: `403`
       + `text`: `"Your token is invalid."`
    3. 406 Not Acceptable, попытка создания пустого поста: object (JSON)
       + `isSuccess`: `false`
       + `code`: `406`
       + `text`: `"Post must contain either text or attachment(s) or link."`
    4. 404 Not Found, ссылка на несуществующий файл вложения: object (JSON)
       + `isSuccess`: `false`
       + `code`: `404`
       + `text`: `"One or more of the attachments do not exist"`
    5. 422 Unprocessable Entity, вложение недопустимого типа: object (JSON)
       + `isSuccess`: `false`
       + `code`: `422`
       + `text`: `"Unsupported attachment type."`

### DELETE /api/posts/{id поста}

Удаление поста.

* Headers:
    + `Authorization`: `Bearer <token>`
* Request body: нет.
* OK Response: `object` (JSON)
    + `isSuccess`: `boolean` - всегда `true`
* Error responses:
    1. 401 Unauthorized, не предоставлен токен авторизации: object (JSON)
       + `isSuccess`: `false`
       + `code`: `401`
       + `text`: `"You need to be authorized to make this request."`
    2. 403 Forbidden, токен не найден в БД либо устарел: object (JSON)
       + `isSuccess`: `false`
       + `code`: `403`
       + `text`: `"Your token is invalid."`
    3. 404 Not Found, пост не найден: object (JSON)
       + `isSuccess`: `false`
       + `code`: `404`
       + `text`: `"Post not found."`
    4. 403 Forbidden, попытка удаления чужого поста: object (JSON)
       + `isSuccess`: `false`
       + `code`: `403`
       + `text`: `"You have no rights to delete this post."`

### POST /api/file

Заливка файла на сервер для его дальнейшего использования как вложение.

* Headers:
    + `Authorization`: `Bearer <token>`
* Request body: (multipart/form-data)
    + `file` - загружаемый файл. Поддерживаемые форматы:
        1. `image/jpeg`
        2. `image/png`
        3. `image/gif`
        4. `image/bmp`
        5. `video/mp4`
        6. `video/3gpp`
        7. `video/x-msvideo`
        8. `video/x-ms-wmv`
* OK Response: `object` (JSON)
    + `isSuccess`: `boolean` - всегда `true`
    + `url`: `string` - URL залитого файла.
* Error responses:
    1. 401 Unauthorized, не предоставлен токен авторизации: object (JSON)
       + `isSuccess`: `false`
       + `code`: `401`
       + `text`: `"You need to be authorized to make this request."`
    2. 403 Forbidden, токен не найден в БД либо устарел: object (JSON)
       + `isSuccess`: `false`
       + `code`: `403`
       + `text`: `"Your token is invalid."`
    3. 500 Internal Server Error, файл повреждён при загрузке либо слишком велик,
        чтобы сервер мог его принять: object (JSON)
       + `isSuccess`: `false`
       + `code`: `500`
       + `text`: `"File was corrupted during transferring, please try again."`
    4. 413 Payload Too Large, слишком большой файл (более 10^8 байт): object (JSON)
       + `isSuccess`: `false`
       + `code`: `403`
       + `text`: `"You have no rights to delete this post."`
    5. 422 Unprocessable Entity, недопустимый формат файла: object (JSON)
       + `isSuccess`: `false`
       + `code`: `422`
       + `text`: `"Unsupported attachment type."`
       
### POST /api/likes/{id поста}

Добавление лайка, если он ещё не поставлен, и удаление в противном случае.

* Headers:
    + `Authorization`: `Bearer <token>`
* Request body: нет.
* OK Response: `object` (JSON)
    + `isSuccess`: `boolean` - всегда `true`
    + `isLikedByMe`: `boolean` - `true`, если лайк добавлен; в ином случае `false`
* Error responses:
    1. 401 Unauthorized, не предоставлен токен авторизации: object (JSON)
       + `isSuccess`: `false`
       + `code`: `401`
       + `text`: `"You need to be authorized to make this request."`
    2. 403 Forbidden, токен не найден в БД либо устарел: object (JSON)
       + `isSuccess`: `false`
       + `code`: `403`
       + `text`: `"Your token is invalid."`
    3. 406 Not Acceptable, попытка создания пустого поста: object (JSON)
       + `isSuccess`: `false`
       + `code`: `406`
       + `text`: `"Post must contain either text or attachment(s) or link."`
    4. 404 Not Found, пост не найден: object (JSON)
       + `isSuccess`: `false`
       + `code`: `404`
       + `text`: `"Post not found."`
       
