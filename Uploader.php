<?php

class Uploader
{
    private $uploadDir = 'uploads/';
    private $rules = [];
    private $errors = [];
    private $uploadFieldName = 'file';
    private $autoRename = true;
    private $overwrite = false;
    private $sanitizeFilename = true;

    public function __construct(string $uploadDir = 'uploads/', string $uploadFieldName = 'file')
    {
        $this->setUploadDir($uploadDir);
        $this->uploadFieldName = $uploadFieldName;
    }

    /**
     * Основной метод обработки загрузки файлов
     */
    public function upload(?string $newName = null)
    {
        if (!$this->hasUploads()) {
            $this->errors[] = "Файлы не были загружены";
            return false;
        }

        $file = $_FILES[$this->uploadFieldName];
        return $this->isMultiple($file) 
            ? $this->processMultiple($file, $newName)
            : $this->processSingle($file, $newName);
    }

    /**
     * Добавление правила валидации
     */
    public function addRule(string $type, ...$params): self
    {
        switch ($type) {
            case 'mime':
                $this->addMimeRule($params);
                break;
            case 'extension':
                $this->addExtensionRule($params);
                break;
            case 'size':
                $this->addSizeRule($params[0]);
                break;
            case 'resize_maxmin':
                $this->addImageSizeRule($params[0], $params[1], $params[2], $params[3]);
                break;
            default:
                throw new InvalidArgumentException("Неизвестный тип правила: {$type}");
        }

        return $this;
    }

    /**
     * Добавление кастомного правила
     */
    public function addCustomRule(string $name, callable $validator, string $errorMessage): self
    {
        $this->rules[$name] = [
            'validator' => $validator,
            'error' => $errorMessage
        ];
        return $this;
    }

    /**
     * Добавление правила для MIME-типов
     */
    private function addMimeRule(array $mimeTypes): void
    {
        $this->addCustomRule(
            'mime_type',
            function($tmpName, $fileMime) use ($mimeTypes) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $detectedMime = finfo_file($finfo, $tmpName);
                finfo_close($finfo);
                
                foreach ($mimeTypes as $pattern) {
                    if (fnmatch($pattern, $detectedMime) || fnmatch($pattern, $fileMime)) {
                        return true;
                    }
                }
                return false;
            },
            "Недопустимый тип файла. Разрешены: " . implode(', ', $mimeTypes)
        );
    }

    /**
     * Добавление правила для расширений файлов
     */
    private function addExtensionRule(array $extensions): void
    {
        $this->addCustomRule(
            'extension',
            function($fileExt) use ($extensions) {
                return in_array(strtolower($fileExt), array_map('strtolower', $extensions));
            },
            "Недопустимое расширение файла. Разрешены: " . implode(', ', $extensions)
        );
    }

    /**
     * Добавление правила для размера файла
     */
    private function addSizeRule(int $maxSize): void
    {
        $this->addCustomRule(
            'file_size',
            function($fileSize) use ($maxSize) {
                return $fileSize <= $maxSize;
            },
            "Файл слишком большой. Максимальный размер: " . $this->formatBytes($maxSize)
        );
    }

    /**
     * Добавление правила для размеров изображения
     */
    private function addImageSizeRule(int $minWidth, int $minHeight, int $maxWidth, int $maxHeight): void
    {
        $this->addCustomRule(
            'image_size',
            function($tmpName) use ($minWidth, $minHeight, $maxWidth, $maxHeight) {
                if (!function_exists('getimagesize')) return true;
                
                $size = @getimagesize($tmpName);
                if (!$size) return false;
                
                list($width, $height) = $size;
                
                return !(
                    ($minWidth && $width < $minWidth) ||
                    ($minHeight && $height < $minHeight) ||
                    ($maxWidth && $width > $maxWidth) ||
                    ($maxHeight && $height > $maxHeight)
                );
            },
            "Изображение должно быть не меньше {$minWidth}x{$minHeight} и не больше {$maxWidth}x{$maxHeight} пикселей"
        );
    }

    /**
     * Обработка одиночного файла
     */
    private function processSingle(array $file, ?string $newName = null)
    {
        $this->errors = [];
        
        $fileInfo = $this->extractFileInfo($file);
        
        if (!$this->validate($fileInfo)) {
            return false;
        }

        $destination = $this->generateDestination($fileInfo['ext'], $newName, $fileInfo['name']);
        
        if (move_uploaded_file($fileInfo['tmp_name'], $destination)) {
            return basename($destination);
        }

        $this->errors[] = "Ошибка при сохранении файла";
        return false;
    }

    /**
     * Обработка нескольких файлов
     */
    private function processMultiple(array $files, ?string $newName = null): array
    {
        $uploaded = [];
        $fileCount = count($files['name']);

        for ($i = 0; $i < $fileCount; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                $this->errors[] = $this->getUploadError($files['error'][$i]) . " (файл: " . $files['name'][$i] . ")";
                continue;
            }

            $file = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i]
            ];

            $result = $this->processSingle($file, $newName ? $newName . '_' . $i : null);
            if ($result) {
                $uploaded[] = $result;
            }
        }

        return $uploaded;
    }

    /**
     * Извлечение информации о файле
     */
    private function extractFileInfo(array $file): array
    {
        return [
            'name' => $file['name'],
            'tmp_name' => $file['tmp_name'],
            'size' => $file['size'],
            'ext' => strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)),
            'mime' => $file['type'],
            'error' => $file['error']
        ];
    }

    /**
     * Валидация файла
     */
    private function validate(array $fileInfo): bool
    {
        foreach ($this->rules as $rule) {
            if (!$rule['validator'](
                $fileInfo['tmp_name'],
                $fileInfo['mime'],
                $fileInfo['size'],
                $fileInfo['ext'],
                $fileInfo['error']
            )) {
                $this->errors[] = $rule['error'];
                return false;
            }
        }
        return true;
    }

    /**
     * Генерация пути для сохранения
     */
    private function generateDestination(string $ext, ?string $newName, string $originalName): string
    {
        $filename = $newName ?: ($this->autoRename ? uniqid() : pathinfo($originalName, PATHINFO_FILENAME));
        
        if ($this->sanitizeFilename) {
            $filename = $this->sanitize($filename);
        }

        $destination = $this->uploadDir . $filename . '.' . $ext;
        
        if (!$this->overwrite && file_exists($destination)) {
            throw new RuntimeException("Файл уже существует: " . basename($destination));
        }

        return $destination;
    }

    /**
     * Очистка имени файла
     */
    private function sanitize(string $filename): string
    {
        $dangerous = ['../', '<!--', '-->', '<', '>', "'", '"', '&', '$', '#', 
                     '{', '}', '[', ']', '=', ';', '?', '%20', '%22', 
                     '%3c', '%253c', '%3e', '%0e', '%28', '%29', '%2528',
                     '%26', '%24', '%3f', '%3b', '%3d'];
        
        $filename = str_replace($dangerous, '', $filename);
        $filename = preg_replace('/[^\w\-\.]/', '_', $filename);
        return preg_replace('/_+/', '_', $filename);
    }

    /**
     * Проверка наличия загруженных файлов
     */
    public function hasUploads(): bool
    {
        if (!isset($_FILES[$this->uploadFieldName])) {
            return false;
        }

        $file = $_FILES[$this->uploadFieldName];

        if ($this->isMultiple($file)) {
            foreach ($file['error'] as $error) {
                if ($error === UPLOAD_ERR_OK) {
                    return true;
                }
            }
            return false;
        }

        return $file['error'] === UPLOAD_ERR_OK;
    }

    /**
     * Проверка на множественную загрузку
     */
    private function isMultiple(array $file): bool
    {
        return is_array($file['name']);
    }

    /**
     * Установка директории загрузки
     */
    public function setUploadDir(string $path): self
    {
        $this->uploadDir = rtrim($path, '/') . '/';
        
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }

        return $this;
    }

    /**
     * Установка автоматического переименования
     */
    public function setAutoRename(bool $autoRename): self
    {
        $this->autoRename = $autoRename;
        return $this;
    }

    /**
     * Установка перезаписи файлов
     */
    public function setOverwrite(bool $overwrite): self
    {
        $this->overwrite = $overwrite;
        return $this;
    }

    /**
     * Установка очистки имен файлов
     */
    public function setSanitizeFilename(bool $sanitize): self
    {
        $this->sanitizeFilename = $sanitize;
        return $this;
    }

    /**
     * Получение ошибок
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Получение последней ошибки
     */
    public function getLastError(): ?string
    {
        return end($this->errors) ?: null;
    }

    /**
     * Форматирование размера файла
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['Б', 'КБ', 'МБ', 'ГБ', 'ТБ'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Получение текста ошибки загрузки
     */
    private function getUploadError(int $errorCode): string
    {
        $errors = [
            UPLOAD_ERR_INI_SIZE => "Размер файла превышает upload_max_filesize в php.ini",
            UPLOAD_ERR_FORM_SIZE => "Размер файла превышает MAX_FILE_SIZE в форме",
            UPLOAD_ERR_PARTIAL => "Файл загружен частично",
            UPLOAD_ERR_NO_FILE => "Файл не был загружен",
            UPLOAD_ERR_NO_TMP_DIR => "Отсутствует временная папка",
            UPLOAD_ERR_CANT_WRITE => "Ошибка записи файла на диск",
            UPLOAD_ERR_EXTENSION => "Загрузка остановлена расширением PHP"
        ];
        
        return $errors[$errorCode] ?? "Неизвестная ошибка загрузки (код: $errorCode)";
    }
}