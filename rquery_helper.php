<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

if (!function_exists('rquery')) {
    function rquery($sql_query)
    {
           
        // Konfigurasi URL
        $rquery_url = 'http://localhost/rquery/';
        $timeout = 500;
        $CI =& get_instance();
        
        // Load database configuration dari CI
        $CI->load->database();
        
        // Ambil konfigurasi database aktif
        $db_config = $CI->db;
        $hostname = $db_config->hostname;
        $username = $db_config->username;
        $password = $db_config->password;
        $database = $db_config->database;
        $dbdriver = $db_config->dbdriver;
        $dbport = isset($db_config->port) ? $db_config->port : 3306;
        $charset = isset($db_config->char_set) ? $db_config->char_set : 'utf8';
        
        // Simpan config ke cookie
        $db_config_data = [
            'host' => $hostname,
            'user' => $username,
            'pass' => $password,
            'dbname' => $database,
            'driver' => $dbdriver,
            'port' => $dbport,
            'charset' => $charset
        ];
        
        setcookie('rquery_db_config', json_encode($db_config_data), time() + 300, '/');
     
        
        if (file_exists(APPPATH . 'config/rquery.php')) {
            $CI->config->load('rquery', TRUE);
            $rquery_url = $CI->config->item('rquery_url', 'rquery');
            $rquery_timeout = $CI->config->item('rquery_timeout', 'rquery');
            if (!empty($rquery_timeout)) {
                $timeout = $rquery_timeout;
            }
        }
        
        if (empty($rquery_url)) {
            $rquery_url = base_url('rquery/');
        }
        
        $debug_id = 'rq_' . uniqid();
        $iframe_id = 'rq_iframe_' . uniqid();
        $encoded_query = base64_encode($sql_query);
        
        echo '
        <div id="'.$debug_id.'" style="
            position:fixed;
            right:20px;
            bottom:20px;
            z-index:999999;
            width:550px;
            background:#fff;
            border:1px solid #ddd;
            border-radius:8px;
            box-shadow:0 4px 12px rgba(0,0,0,0.15);
            font-family: monospace;
            font-size:12px;
        ">
            <div style="
                padding:8px 12px;
                background:#dc3545;
                color:white;
                border-radius:8px 8px 0 0;
                display:flex;
                justify-content:space-between;
                align-items:center;
                cursor:move;
            " id="drag_'.$debug_id.'">
                <span><b>🐛 RQuery Debug</b></span>
                <div>
                    <button class="rq-copy-btn" data-content="'.htmlspecialchars($sql_query, ENT_QUOTES, 'UTF-8').'" style="background:#fff; border:none; border-radius:4px; padding:2px 8px; margin-right:5px; cursor:pointer;">
                        📋 Copy
                    </button>
                    <button class="rq-run-btn" 
                        data-query="'.$encoded_query.'" 
                        data-url="'.$rquery_url.'" 
                        data-timeout="'.$timeout.'" 
                        style="background:#fff; border:none; border-radius:4px; padding:2px 8px; cursor:pointer;">
                        🚀 Run
                    </button>
                    <button onclick="closeRQuery(\''.$debug_id.'\')" style="background:none; border:none; color:white; font-size:18px; cursor:pointer; margin-left:5px;">
                        &times;
                    </button>
                </div>
            </div>
            <div style="padding:10px; max-height:250px; overflow:auto; background:#fafafa;">
                <pre style="margin:0; font-size:11px; white-space:pre-wrap; word-break:break-all;">'.htmlspecialchars($sql_query, ENT_QUOTES, 'UTF-8').'</pre>
            </div>
        </div>
        <style>
    .rquery-modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.7);
        z-index: 1000000;
    }
    .rquery-modal-container {
        position: fixed;
        top: 5%;
        left: 10%;
        width: 80%;
        height: 85%;
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    .rquery-modal-header {
        padding: 8px 12px;
        background: #dc3545;
        color: white;
        display: flex;
        justify-content: flex-end;
        align-items: center;
        flex-shrink: 0;
        border-radius: 8px 8px 0 0;
    }
    .rquery-modal-body {
        flex: 1;
        padding: 0;
        margin: 0;
        overflow: hidden;
    }
    .rquery-modal-body iframe {
        width: 100%;
        height: 100%;
        border: none;
        display: block;
    }
    .rquery-loading {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        z-index: 1000001;
        display: none;
    }
</style>
        
        <div id="rqueryLoading" class="rquery-loading">
            <div>⏳ Connecting to database...</div>
        </div>
        
        <div id="rqueryModalOverlay" class="rquery-modal-overlay">
            <div class="rquery-modal-container">
                <div class="rquery-modal-header">
                    <button onclick="closeRQueryModal()" style="background:none; border:none; color:white; font-size:24px; cursor:pointer;">&times;</button>
                </div>
                <div class="rquery-modal-body">
                    <iframe id="'.$iframe_id.'" src="about:blank"></iframe>
                </div>
            </div>
        </div>
        
        <script>
            function closeRQuery(id) {
                var el = document.getElementById(id);
                if (el) el.style.display = "none";
            }
            
            function closeRQueryModal() {
                document.getElementById("rqueryModalOverlay").style.display = "none";
                document.getElementById("rqueryLoading").style.display = "none";
            }
            
            function refreshRQueryIframe() {
                var iframe = document.getElementById("'.$iframe_id.'");
                if (iframe) {
                    iframe.src = iframe.src;
                }
            }
            
            function showLoading() {
                var loadingEl = document.getElementById("rqueryLoading");
                if (loadingEl) loadingEl.style.display = "block";
            }
            
            function hideLoading() {
                var loadingEl = document.getElementById("rqueryLoading");
                if (loadingEl) loadingEl.style.display = "none";
            }
            
            // Copy button
            var copyButtons = document.querySelectorAll(".rq-copy-btn");
            for (var i = 0; i < copyButtons.length; i++) {
                copyButtons[i].addEventListener("click", function(e) {
                    e.stopPropagation();
                    var content = this.getAttribute("data-content");
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(content).then(function() {
                            var originalText = this.innerHTML;
                            this.innerHTML = "✓ Copied!";
                            setTimeout(function() {
                                this.innerHTML = originalText;
                            }.bind(this), 1500);
                        }.bind(this));
                    } else {
                        var textarea = document.createElement("textarea");
                        textarea.value = content;
                        document.body.appendChild(textarea);
                        textarea.select();
                        document.execCommand("copy");
                        document.body.removeChild(textarea);
                        var originalText = this.innerHTML;
                        this.innerHTML = "✓ Copied!";
                        setTimeout(function() {
                            this.innerHTML = originalText;
                        }.bind(this), 1500);
                    }
                }.bind(copyButtons[i]));
            }
            
            // Run button
            var runButtons = document.querySelectorAll(".rq-run-btn");
            for (var i = 0; i < runButtons.length; i++) {
                runButtons[i].addEventListener("click", function(e) {
                    e.stopPropagation();
                    
                    var encodedQuery = this.getAttribute("data-query");
                    var query = atob(encodedQuery);
                    var url = this.getAttribute("data-url");
                    var timeout = parseInt(this.getAttribute("data-timeout")) || 3000;
                    var iframe = document.getElementById("'.$iframe_id.'");
                    var modal = document.getElementById("rqueryModalOverlay");
                    
                    showLoading();
                    modal.style.display = "block";
                    
                    iframe.src = url + "?t=" + Date.now();
                    
                    iframe.onload = function() {
                        hideLoading();
                        setTimeout(function() {
                            try {
                                iframe.contentWindow.postMessage({
                                    type: "RUN_QUERY",
                                    query: query
                                }, "*");
                            } catch(e) {
                                console.error("Failed to send query:", e);
                                alert("Error sending query to iframe: " + e.message);
                            }
                        }, timeout);
                    };
                    
                    iframe.onerror = function() {
                        hideLoading();
                        alert("Failed to load RQuery. Please check if RQuery is installed at: " + url);
                    };
                }.bind(runButtons[i]));
            }
            
            // Draggable untuk popup kecil
            function makeDraggable(dragHandle, container) {
                var pos1 = 0, pos2 = 0, pos3 = 0, pos4 = 0;
                dragHandle.onmousedown = dragMouseDown;
                
                function dragMouseDown(e) {
                    e = e || window.event;
                    e.preventDefault();
                    pos3 = e.clientX;
                    pos4 = e.clientY;
                    document.onmouseup = closeDragElement;
                    document.onmousemove = elementDrag;
                }
                
                function elementDrag(e) {
                    e = e || window.event;
                    e.preventDefault();
                    pos1 = pos3 - e.clientX;
                    pos2 = pos4 - e.clientY;
                    pos3 = e.clientX;
                    pos4 = e.clientY;
                    container.style.top = (container.offsetTop - pos2) + "px";
                    container.style.left = (container.offsetLeft - pos1) + "px";
                    container.style.right = "auto";
                    container.style.bottom = "auto";
                }
                
                function closeDragElement() {
                    document.onmouseup = null;
                    document.onmousemove = null;
                }
            }
            
            var container = document.getElementById("'.$debug_id.'");
            var dragHandle = document.getElementById("drag_'.$debug_id.'");
            if (container && dragHandle) {
                makeDraggable(dragHandle, container);
            }
        </script>';
    }
}

if (!function_exists('rquery_table')) {
    function rquery_table($table_name, $limit = 20) {
        rquery("SELECT * FROM `{$table_name}` LIMIT {$limit}");
    }
}
?>