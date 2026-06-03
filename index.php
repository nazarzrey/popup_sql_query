<?php
session_start();

// Ambil database config dari cookie
$db_config = null;
if (isset($_COOKIE['rquery_db_config'])) {
    $db_config = json_decode($_COOKIE['rquery_db_config'], true);
    
    if ($db_config && is_array($db_config)) {
        define('DB_HOST', $db_config['host']);
        define('DB_USER', $db_config['user']);
        define('DB_PASS', $db_config['pass']);
        define('DB_NAME', $db_config['dbname']);
        define('DB_PORT', isset($db_config['port']) ? $db_config['port'] : 3306);
        define('DB_CHARSET', isset($db_config['charset']) ? $db_config['charset'] : 'utf8mb4');
    }
}

// Fallback config
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'test');
    define('DB_PORT', 3306);
    define('DB_CHARSET', 'utf8mb4');
}

define('MAX_RESULTS', 1000);

// CSRF protection
if (empty($_SESSION['rquery_token'])) {
    $_SESSION['rquery_token'] = bin2hex(random_bytes(32));
}

$active_db = DB_NAME;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RQuery - SQL Runner</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 0;
            margin: 0;
            height: 100vh;
            overflow: hidden;
        }
        
        .app {
            display: flex;
            flex-direction: column;
            height: 100vh;
        }
        
        .query-section {
            background: #fff;
            border-bottom: 1px solid #e0e0e0;
            padding: 15px 20px;
            flex-shrink: 0;
        }
        
        .query-editor {
            width: 100%;
            font-family: 'Consolas', 'Courier New', monospace;
            font-size: 13px;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            resize: vertical;
            background: #fafafa;
        }
        
        .query-editor:focus {
            outline: none;
            border-color: #4caf50;
        }
        
        .button-group {
            margin-top: 12px;
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
        }
        
        .btn-execute {
            background: #4caf50;
            color: white;
        }
        
        .btn-execute:hover {
            background: #45a049;
        }
        
        .btn-clear {
            background: #6c757d;
            color: white;
        }
        
        .btn-clear:hover {
            background: #5a6268;
        }
        
        .results-section {
            flex: 1;
            background: #fff;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            margin: 0;
        }
        
        .results-header {
            padding: 12px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }
        
        .results-title {
            font-weight: 600;
            color: #333;
            font-size: 13px;
        }
        
        .result-count {
            font-size: 12px;
            color: #666;
        }
        
        .table-wrapper {
            flex: 1;
            overflow: auto;
            padding: 0;
        }
        
        .result-table {
            width: 100%;
            border-collapse: collapse;
            font-family: 'Consolas', monospace;
            font-size: 12px;
        }
        
        .result-table thead {
            background: #f5f5f5;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .result-table th {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 2px solid #ddd;
            font-weight: 600;
            background: #f8f9fa;
            cursor: pointer;
            user-select: none;
            white-space: nowrap;
        }
        
        .result-table th:hover {
            background: #e8e8e8;
        }
        
        .result-table th .sort-icon {
            display: inline-block;
            margin-left: 5px;
            font-size: 10px;
            opacity: 0.5;
        }
        
        .result-table th.sort-asc .sort-icon {
            opacity: 1;
            content: "▲";
        }
        
        .result-table th.sort-desc .sort-icon {
            opacity: 1;
            content: "▼";
        }
        
        .result-table td {
            padding: 8px 12px;
            border-bottom: 1px solid #eee;
        }
        
        .result-table tr:hover {
            background: #f5f5f5;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px 20px;
            border-left: 4px solid #dc3545;
            margin: 20px;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px 20px;
            border-left: 4px solid #28a745;
            margin: 20px;
        }
        
        .status-bar {
            background: #333;
            color: white;
            padding: 6px 20px;
            font-size: 11px;
            font-family: monospace;
            display: flex;
            justify-content: space-between;
            flex-shrink: 0;
        }
        
        .info-row {
            padding: 8px 20px;
            background: #e3f2fd;
            font-size: 12px;
            color: #1565c0;
            display: flex;
            justify-content: space-between;
            border-bottom: 1px solid #bbdefb;
            flex-shrink: 0;
        }
    </style>
</head>
<body>
    <div class="app">
        <div class="query-section">
            <textarea id="sqlQuery" class="query-editor" rows="4" placeholder="Enter SQL query...&#10;Example: SELECT * FROM users LIMIT 10">SELECT DATABASE() as current_database, NOW() as server_time;</textarea>
            <div class="button-group">
                <button onclick="executeQuery()" class="btn btn-execute">🚀 Execute (Ctrl+Enter)</button>
                <button onclick="clearQuery()" class="btn btn-clear">🗑️ Clear</button>
            </div>
        </div>
        
        <div class="results-section">
            <div class="results-header">
                <span class="results-title">📋 Query Results</span>
                <span class="result-count" id="resultCount"></span>
            </div>
            <div class="table-wrapper" id="tableWrapper">
                <div id="resultContent">
                    <div class="success-message">Ready to execute queries</div>
                </div>
            </div>
        </div>
        
        <div class="status-bar">
            <span id="statusText">Connected to: <?php echo htmlspecialchars($active_db); ?></span>
            <span id="queryTime"></span>
        </div>
    </div>

    <script>
        const RQUERY_TOKEN = '<?php echo $_SESSION['rquery_token']; ?>';
        let currentData = null;
        let currentSort = { column: null, direction: 'asc' };
        
        // Listen for query from parent
        window.addEventListener('message', function(event) {
            if (event.data && event.data.type === "RUN_QUERY") {
                document.getElementById('sqlQuery').value = event.data.query;
                setTimeout(executeQuery, 300);
            }
        });
        
        window.parent.postMessage({ type: "RQUERY_READY" }, "*");
        
        function executeQuery() {
            var query = document.getElementById('sqlQuery').value.trim();
            if (!query) {
                alert('Please enter a query');
                return;
            }
            
            updateStatus('Executing...');
            var startTime = Date.now();
            
            fetch('ajax_handler.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=execute&query=' + encodeURIComponent(btoa(query)) + '&token=' + RQUERY_TOKEN
            })
            .then(res => res.json())
            .then(data => {
                var elapsed = Date.now() - startTime;
                document.getElementById('queryTime').innerHTML = elapsed + 'ms';
                
                if (data.success) {
                    updateStatus('Success');
                    currentData = data;
                    currentSort = { column: null, direction: 'asc' };
                    displayResults(data);
                } else {
                    updateStatus('Error');
                    displayError(data.error);
                    currentData = null;
                }
            })
            .catch(err => {
                updateStatus('Network error');
                displayError('Connection failed: ' + err.message);
                currentData = null;
            });
        }
        
        function displayResults(data) {
            var resultDiv = document.getElementById('resultContent');
            var countSpan = document.getElementById('resultCount');
            var wrapper = document.getElementById('tableWrapper');
            
            if (data.type === 'select') {
                if (data.rows && data.rows.length > 0) {
                    var html = '<table class="result-table" id="resultTable"><thead><tr>';
                    for (var i = 0; i < data.columns.length; i++) {
                        html += '<th onclick="sortTable(' + i + ', \'' + data.columns[i] + '\')">' + 
                                escapeHtml(data.columns[i]) + 
                                '<span class="sort-icon">⇅</span></th>';
                    }
                    html += '</tr></thead><tbody id="tableBody"></tbody></table>';
                    resultDiv.innerHTML = html;
                    
                    // Render data
                    renderTableBody(data.rows, data.columns);
                    countSpan.innerHTML = data.rows.length + ' row(s)';
                } else {
                    resultDiv.innerHTML = '<div class="success-message">Query returned 0 rows</div>';
                    countSpan.innerHTML = '0 rows';
                }
            } else if (data.type === 'affected') {
                var msg = '✅ Query executed successfully!<br>Affected rows: ' + data.affectedRows;
                if (data.insertId) msg += '<br>Insert ID: ' + data.insertId;
                resultDiv.innerHTML = '<div class="success-message">' + msg + '</div>';
                countSpan.innerHTML = data.affectedRows + ' row(s) affected';
            } else {
                resultDiv.innerHTML = '<div class="success-message">✅ Query executed successfully</div>';
                countSpan.innerHTML = '';
            }
        }
        
        function renderTableBody(rows, columns) {
            var tbody = document.getElementById('tableBody');
            if (!tbody) return;
            
            var html = '';
            for (var r = 0; r < rows.length; r++) {
                html += '<tr>';
                for (var c = 0; c < columns.length; c++) {
                    var val = rows[r][columns[c]];
                    html += '<td>' + (val === null ? '<i>NULL</i>' : escapeHtml(String(val))) + '</td>';
                }
                html += '</tr>';
            }
            tbody.innerHTML = html;
        }
        
        function sortTable(colIndex, colName) {
            if (!currentData || !currentData.rows) return;
            
            // Toggle sort direction
            if (currentSort.column === colIndex) {
                currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
            } else {
                currentSort.column = colIndex;
                currentSort.direction = 'asc';
            }
            
            // Sort data
            var sortedRows = [...currentData.rows];
            sortedRows.sort(function(a, b) {
                var valA = a[colName];
                var valB = b[colName];
                
                // Handle null values
                if (valA === null) valA = '';
                if (valB === null) valB = '';
                
                // Handle numbers
                if (!isNaN(valA) && !isNaN(valB) && valA !== '' && valB !== '') {
                    valA = parseFloat(valA);
                    valB = parseFloat(valB);
                }
                
                if (valA < valB) return currentSort.direction === 'asc' ? -1 : 1;
                if (valA > valB) return currentSort.direction === 'asc' ? 1 : -1;
                return 0;
            });
            
            // Update sort icons
            var headers = document.querySelectorAll('#resultTable th');
            for (var i = 0; i < headers.length; i++) {
                headers[i].classList.remove('sort-asc', 'sort-desc');
                var icon = headers[i].querySelector('.sort-icon');
                if (icon) icon.innerHTML = '⇅';
            }
            
            var currentHeader = headers[colIndex];
            if (currentHeader) {
                currentHeader.classList.add('sort-' + currentSort.direction);
                var currentIcon = currentHeader.querySelector('.sort-icon');
                if (currentIcon) {
                    currentIcon.innerHTML = currentSort.direction === 'asc' ? '▲' : '▼';
                }
            }
            
            // Re-render
            renderTableBody(sortedRows, currentData.columns);
        }
        
        function displayError(error) {
            document.getElementById('resultContent').innerHTML = '<div class="error-message">❌ Error: ' + escapeHtml(error) + '</div>';
            document.getElementById('resultCount').innerHTML = '';
        }
        
        function clearQuery() {
            document.getElementById('sqlQuery').value = '';
        }
        
        function updateStatus(msg) {
            document.getElementById('statusText').innerHTML = msg;
        }
        
        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }
        
        // Keyboard shortcut
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                executeQuery();
            }
        });
    </script>
</body>
</html>