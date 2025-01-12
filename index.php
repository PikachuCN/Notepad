<?php
// 确保有保存目录
if (!file_exists('updata')) {
    mkdir('updata', 0777, true);
}

// 图片压缩和转换函数
function compressAndConvertToJpg($source, $destination, $quality = 80) {
    $info = getimagesize($source);
    if ($info === false) return false;
    
    switch ($info[2]) {
        case IMAGETYPE_JPEG:
            $image = imagecreatefromjpeg($source);
            break;
        case IMAGETYPE_PNG:
            $image = imagecreatefrompng($source);
            break;
        case IMAGETYPE_GIF:
            $image = imagecreatefromgif($source);
            break;
        case IMAGETYPE_WEBP:
            $image = imagecreatefromwebp($source);
            break;
        default:
            return false;
    }
    
    if ($image === false) return false;
    
    // 保持PNG透明度
    if ($info[2] === IMAGETYPE_PNG) {
        imagealphablending($image, true);
        imagesavealpha($image, true);
    }
    
    return imagejpeg($image, $destination, $quality);
}

// 在文件开头附近添加
function getSafeFileName($id) {
    return preg_replace('/[^a-zA-Z0-9]/', '', $id) . '.txt';
}

// 处理保存请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (isset($_POST['content']) && isset($_POST['noteId'])) {
        // 保存内容
        $noteId = $_POST['noteId'];
        $content = $_POST['content'];
        $filename = 'updata/' . getSafeFileName($noteId);
        file_put_contents($filename, $content);
        echo json_encode(['success' => true]);
        exit;
    } elseif (isset($_POST['image'])) {
        // 处理粘贴的图片上传
        $data = $_POST['image'];
        
        list($type, $data) = explode(';', $data);
        list(, $data) = explode(',', $data);
        $data = base64_decode($data);
        
        if (!preg_match('/^data:image\/(jpeg|png|gif|webp)/i', $type)) {
            echo json_encode([
                'success' => false,
                'error' => '只允许上传图片文件'
            ]);
            exit;
        }
        
        $temp_file = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($temp_file, $data);
        $filename = 'updata/' . uniqid() . '.jpg';
        
        if (compressAndConvertToJpg($temp_file, $filename, 80)) {
            unlink($temp_file);
            echo json_encode([
                'success' => true,
                'url' => $filename
            ]);
        } else {
            unlink($temp_file);
            echo json_encode([
                'success' => false,
                'error' => '图片处理失败'
            ]);
        }
        exit;
    } elseif (isset($_FILES['file'])) {
        // 处理文件上传
        $file = $_FILES['file'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // 如果是图片文件
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $filename = 'updata/' . uniqid() . '.jpg';
            
            if (compressAndConvertToJpg($file['tmp_name'], $filename, 80)) {
                echo json_encode([
                    'success' => true,
                    'url' => $filename,
                    'filename' => pathinfo($file['name'], PATHINFO_FILENAME) . '.jpg'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => '图片处理失败'
                ]);
            }
            exit;
        }
        
        // 处理其他类型文件
        $allowed_types = [
            'txt', 'pdf', 'doc', 'docx', 'xls', 'xlsx',
            'zip', 'rar', '7z'
        ];
        
        if (!in_array($extension, $allowed_types)) {
            echo json_encode([
                'success' => false,
                'error' => '不允许上传该类型的文件'
            ]);
            exit;
        }
        
        if ($file['size'] > 20 * 1024 * 1024) {
            echo json_encode([
                'success' => false,
                'error' => '文件大小不能超过20MB'
            ]);
            exit;
        }
        
        $filename = 'updata/' . uniqid() . '.' . $extension;
        
        if (move_uploaded_file($file['tmp_name'], $filename)) {
            echo json_encode([
                'success' => true,
                'url' => $filename,
                'filename' => $file['name']
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => '保存文件失败'
            ]);
        }
        exit;
    }
}

// 修改读取内容的部分
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['note'])) {
    $noteId = $_GET['note'];
    $filename = 'updata/' . getSafeFileName($noteId);
    $content = file_exists($filename) ? file_get_contents($filename) : '';
    
    // 如果是AJAX请求，直接返回内容
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo $content;
        exit;
    }
}

// 默认显示的内容
$noteId = isset($_GET['note']) ? $_GET['note'] : 'default';
$content = '';
$filename = 'updata/' . getSafeFileName($noteId);
if (file_exists($filename)) {
    $content = file_get_contents($filename);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>在线记事本</title>
    <meta charset="UTF-8">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <style>
        html, body {
            margin: 0;
            padding: 0;
            height: 100vh;
            overflow: hidden;
        }
        #editor {
            height: calc(100vh - 42px);
            margin: 0;
        }
        .ql-editor {
            font-size: 16px;
            min-height: 100%;
        }
        .ql-editor img {
            max-width: 100%;
            max-height: 400px;
            object-fit: contain;
            display: block;
            margin: 10px auto;
        }
        .ql-toolbar.ql-snow {
            border-top: none;
            border-left: none;
            border-right: none;
            padding: 8px;
        }
        .ql-container.ql-snow {
            border: none;
        }
        #tabs-container {
            background: #f3f3f3;
            border-bottom: 1px solid #ccc;
        }
        #tabs {
            display: flex;
            padding: 5px 5px 0 5px;
            overflow-x: auto;
        }
        #tab-list {
            display: flex;
            flex-grow: 1;
        }
        .tab {
            padding: 8px 15px;
            background: #e3e3e3;
            border: 1px solid #ccc;
            border-bottom: none;
            margin-right: 5px;
            border-radius: 4px 4px 0 0;
            cursor: pointer;
            position: relative;
            user-select: none;
        }
        .tab.active {
            background: white;
            margin-bottom: -1px;
            padding-bottom: 9px;
        }
        .tab-close {
            margin-left: 8px;
            color: #999;
            cursor: pointer;
        }
        #new-tab {
            padding: 8px 12px;
            background: #e3e3e3;
            border: 1px solid #ccc;
            border-bottom: none;
            border-radius: 4px 4px 0 0;
            cursor: pointer;
        }
        #editor {
            height: calc(100vh - 85px);
        }
    </style>
</head>
<body>
    <div id="tabs-container">
        <div id="tabs">
            <div id="tab-list"></div>
            <button id="new-tab">+</button>
        </div>
    </div>
    <div id="editor"><?php echo $content; ?></div>

    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        var quill = new Quill('#editor', {
            theme: 'snow',
            modules: {
                toolbar: {
                    container: [
                        [{ 'header': [1, 2, 3, false] }],
                        ['bold', 'italic', 'underline'],
                        ['image']
                    ],
                    handlers: {
                        'image': function() {
                            const input = document.createElement('input');
                            input.setAttribute('type', 'file');
                            input.setAttribute('accept', 'image/*');
                            input.click();

                            input.onchange = function() {
                                const file = input.files[0];
                                if (file) {
                                    const formData = new FormData();
                                    formData.append('file', file);

                                    $.ajax({
                                        url: window.location.href,
                                        type: 'POST',
                                        data: formData,
                                        processData: false,
                                        contentType: false,
                                        success: function(response) {
                                  
                                            if (response.success) {
                                                const range = quill.getSelection(true);
                                                quill.insertEmbed(range.index, 'image', response.url);
                                            } else {
                                                alert(response.error || '上传失败');
                                            }
                                        }
                                    });
                                }
                            };
                        }
                    }
                }
            }
        });

        // 处理文件拖放
        quill.root.addEventListener('drop', function(event) {
            event.preventDefault();
            const files = event.dataTransfer.files;
            handleFiles(files);
        });

        // 处理文件粘贴
        document.addEventListener('paste', function(event) {
            const items = (event.clipboardData || event.originalEvent.clipboardData).items;
            for (let item of items) {
                if (item.kind === 'file') {
                    const file = item.getAsFile();
                    handleFiles([file]);
                }
            }
        });

        function handleFiles(files) {
            for (let file of files) {
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        uploadImage(e.target.result);
                    };
                    reader.readAsDataURL(file);
                } else {
                    uploadFile(file);
                }
            }
        }

        function uploadFile(file) {
            const formData = new FormData();
            formData.append('file', file);

            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
       
                    if (response.success) {
                        let range = quill.getSelection() || { index: quill.getLength() };
                        const text = `📎 ${response.filename}`;
                        quill.insertText(range.index, text, 'link', response.url);
                        quill.insertText(range.index + text.length, ' ', 'normal');
                    } else {
                        alert(response.error || '上传失败');
                    }
                }
            });
        }

        function uploadImage(dataUrl) {
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    image: dataUrl
                },
                success: function(response) {
                   
                    if (response.success) {
                        let range = quill.getSelection() || { index: quill.getLength() };
                        quill.insertEmbed(range.index, 'image', response.url);
                    }
                }
            });
        }

        // 自动保存功能
        let saveTimeout;
        quill.on('text-change', function() {
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(saveContent, 1000);
        });

        function saveContent() {
            let content = quill.root.innerHTML;
            $.post(window.location.href, {
                content: content,
                noteId: tabs.current
            }, function(response) {
                console.log('内容已保存');
            });
        }

        const tabs = {
            list: JSON.parse(localStorage.getItem('tabs') || '[]'),
            current: localStorage.getItem('currentTab') || null,

            init() {
                if (this.list.length === 0) {
                    this.createTab();
                }
                
                // 从URL读取指定的笔记
                const urlParams = new URLSearchParams(window.location.search);
                const noteId = urlParams.get('note');
                if (noteId) {
                    if (!this.list.find(tab => tab.id === noteId)) {
                        this.list.push({
                            id: noteId,
                            name: `笔记 ${noteId.slice(0, 6)}`
                        });
                    }
                    this.current = noteId;
                }

                this.render();
                this.bindEvents();
            },

            createTab() {
                const id = Math.random().toString(36).substr(2, 9);
                const name = `笔记 ${id.slice(0, 6)}`;
                this.list.push({ id, name });
                this.current = id;
                this.save();
                this.render();
                this.loadContent(id);
            },

            closeTab(id) {
                const index = this.list.findIndex(tab => tab.id === id);
                if (index > -1) {
                    this.list.splice(index, 1);
                    if (this.current === id) {
                        this.current = this.list[Math.max(0, index - 1)]?.id;
                    }
                    this.save();
                    this.render();
                    if (this.current) {
                        this.loadContent(this.current);
                    }
                }
            },

            switchTab(id) {
                this.current = id;
                this.save();
                this.render();
                this.loadContent(id);
            },

            loadContent(id) {
                // 更新 URL，使用干净的基础URL
                const baseUrl = window.location.pathname;
                const url = new URL(baseUrl, window.location.origin);
                url.searchParams.set('note', id);
                window.history.replaceState({}, '', url.toString());
                
                $.ajax({
                    url: baseUrl,  // 使用干净的基础URL
                    type: 'GET',
                    data: { note: id },
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(response) {
                        quill.setContents([]);
                        quill.clipboard.dangerouslyPasteHTML(0, response);
                    }
                });
            },

            save() {
                localStorage.setItem('tabs', JSON.stringify(this.list));
                localStorage.setItem('currentTab', this.current);
            },

            render() {
                const container = document.getElementById('tab-list');
                container.innerHTML = this.list.map(tab => `
                    <div class="tab ${tab.id === this.current ? 'active' : ''}" 
                         data-id="${tab.id}">
                        ${tab.name}
                        <span class="tab-close">×</span>
                    </div>
                `).join('');
            },

            bindEvents() {
                document.getElementById('new-tab').addEventListener('click', () => this.createTab());
                
                document.getElementById('tab-list').addEventListener('click', (e) => {
                    const tab = e.target.closest('.tab');
                    if (!tab) return;
                    
                    if (e.target.classList.contains('tab-close')) {
                        this.closeTab(tab.dataset.id);
                    } else {
                        this.switchTab(tab.dataset.id);
                    }
                });
            }
        };

        // 初始化选项卡
        tabs.init();
    </script>
</body>
</html>
