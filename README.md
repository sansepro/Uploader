# Документация к классу Uploader

[![Source Code](https://img.shields.io/badge/source-sansepro/Uploader-blue.svg?style=flat-square)](https://github.com/sansepro/Uploader)
[![Latest Version](https://img.shields.io/packagist/v/sansepro/Uploader.svg?style=flat-square)](https://github.com/sansepro/Uploader/releases)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](https://github.com/sansepro/Uploader/blob/master/LICENSE)
[![Build Status](https://github.com/sansepro/Uploader/actions/workflows/ci.yml/badge.svg)](https://github.com/sansepro/Uploader/actions/workflows/ci.yml)
[![Coverage Status](https://img.shields.io/scrutinizer/coverage/g/sansepro/Uploader.svg?style=flat-square)](https://scrutinizer-ci.com/g/sansepro/Uploader/code-structure)
[![Quality Score](https://img.shields.io/scrutinizer/g/sansepro/Uploader.svg?style=flat-square)](https://scrutinizer-ci.com/g/sansepro/Uploader)
[![Total Downloads](https://img.shields.io/packagist/dt/sansepro/Uploader.svg?style=flat-square)](https://packagist.org/packages/sansepro/Uploader)

## Содержание
1. [Введение](#введение)
2. [Установка и инициализация](#установка-и-инициализация)
3. [Основные методы](#основные-методы)
4. [Добавление правил валидации](#добавление-правил-валидации)
5. [Настройки загрузчика](#настройки-загрузчика)
6. [Обработка ошибок](#обработка-ошибок)
7. [Примеры использования](#примеры-использования)
8. [Безопасность](#безопасность)

## Введение

Класс `Uploader` предоставляет удобный и безопасный способ загрузки файлов на сервер с возможностью гибкой валидации. Основные особенности:

- Поддержка загрузки одного и нескольких файлов
- Гибкая система правил валидации
- Автоматическое переименование файлов
- Защита от опасных имен файлов
- Подробные сообщения об ошибках на русском языке

## Установка и инициализация

Для использования класса достаточно подключить файл с классом:

```php
require_once 'Uploader.php';
```

Инициализация загрузчика:

```php
$uploader = new Uploader(
    string $uploadDir = 'uploads/', 
    string $uploadFieldName = 'file'
);
```

Параметры:
- `$uploadDir` - директория для загрузки файлов (по умолчанию 'uploads/')
- `$uploadFieldName` - имя поля формы для загрузки (по умолчанию 'file')

## Основные методы

### `upload(?string $newName = null)`

Основной метод для обработки загрузки файлов.

```php
$result = $uploader->upload('имя_файла');
```

Параметры:
- `$newName` - базовое имя файла без расширения (если null, будет сгенерировано автоматически)

Возвращает:
- Для одиночного файла: имя загруженного файла или `false` при ошибке
- Для нескольких файлов: массив имен загруженных файлов

### `hasUploads()`

Проверяет наличие загруженных файлов.

```php
if ($uploader->hasUploads()) {
    // есть загруженные файлы
}
```

## Добавление правил валидации

### Стандартные правила

#### Проверка MIME-типов
```php
$uploader->addRule('mime', ['image/*', 'application/pdf']);
```

#### Проверка расширений файлов
```php
$uploader->addRule('extension', ['jpg', 'png', 'pdf']);
```

#### Проверка размера файла
```php
$uploader->addRule('size', 5 * 1024 * 1024); // 5 МБ
```

#### Проверка размеров изображения
```php
// мин. ширина, мин. высота, макс. ширина, макс. высота
$uploader->addRule('resize_maxmin', 100, 100, 4000, 4000);
```

### Кастомные правила

```php
$uploader->addCustomRule(
    'rule_name',
    function($tmpName, $mime, $size, $ext, $error) {
        // ваша логика проверки
        return true; // или false при ошибке
    },
    "Сообщение об ошибке"
);
```

## Настройки загрузчика

### `setUploadDir(string $path)`
Установка директории для загрузки.

```php
$uploader->setUploadDir('new_uploads/');
```

### `setAutoRename(bool $autoRename)`
Включение/выключение автоматического переименования.

```php
$uploader->setAutoRename(true);
```

### `setOverwrite(bool $overwrite)`
Разрешение перезаписи существующих файлов.

```php
$uploader->setOverwrite(false);
```

### `setSanitizeFilename(bool $sanitize)`
Включение/выключение очистки имен файлов.

```php
$uploader->setSanitizeFilename(true);
```

## Обработка ошибок

### `getErrors()`
Получение массива ошибок.

```php
$errors = $uploader->getErrors();
```

### `getLastError()`
Получение последней ошибки.

```php
$error = $uploader->getLastError();
```

## Примеры использования

### Пример 1: Базовая загрузка
```php
$uploader = new Uploader();
$uploader->addRule('extension', ['jpg', 'png']);
$result = $uploader->upload();

if ($result) {
    echo "Файл загружен: $result";
} else {
    print_r($uploader->getErrors());
}
```

### Пример 2: Загрузка изображений с проверкой размеров
```php
$uploader = new Uploader('images/');
$uploader->addRule('mime', ['image/*'])
         ->addRule('resize_maxmin', 100, 100, 2000, 2000)
         ->setAutoRename(true);

$result = $uploader->upload('avatar');
```

### Пример 3: Кастомное правило
```php
$uploader->addCustomRule(
    'no_exe',
    function($tmpName, $mime, $size, $ext, $error) {
        return $ext !== 'exe';
    },
    "Загрузка EXE-файлов запрещена"
);
```

## Безопасность

Класс включает несколько механизмов безопасности:

1. **Санитайзинг имен файлов** - удаление опасных символов и последовательностей
2. **Проверка MIME-типов** - защита от подмены типа файла
3. **Ограничение расширений** - запрет опасных типов файлов
4. **Защита от перезаписи** - предотвращение случайной перезаписи важных файлов

Для максимальной безопасности рекомендуется:
- Всегда проверять MIME-типы
- Ограничивать допустимые расширения
- Использовать автоматическое переименование
- Не разрешать перезапись файлов
