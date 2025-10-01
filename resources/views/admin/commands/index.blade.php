<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin Commands - Exeat Management</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .command-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border: none;
            border-radius: 12px;
        }
        .command-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .btn-command {
            border-radius: 8px;
            padding: 12px 24px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-command:hover {
            transform: translateY(-1px);
        }
        .output-container {
            background-color: #1e1e1e;
            color: #ffffff;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            max-height: 400px;
            overflow-y: auto;
        }
        .status-badge {
            border-radius: 20px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .header-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .loading-spinner {
            display: none;
        }
        .command-description {
            color: #6c757d;
            font-size: 14px;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Header Section -->
        <div class="header-section">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2"><i class="fas fa-terminal me-3"></i>Admin Command Center</h1>
                    <p class="mb-0 opacity-75">Manually execute scheduled exeat management commands</p>
                </div>
                <div class="col-md-4 text-end">
                    <span class="status-badge bg-success">
                        <i class="fas fa-clock me-1"></i>
                        Scheduled: Every Hour
                    </span>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <!-- Command Cards -->
        <div class="row g-4">
            <!-- Check Overdue Command -->
            <div class="col-lg-4">
                <div class="card command-card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-warning bg-opacity-10 p-3 rounded-circle me-3">
                                <i class="fas fa-search text-warning fs-4"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-1">Check Overdue</h5>
                                <small class="text-muted">Monitor Command</small>
                            </div>
                        </div>
                        <p class="command-description">
                            Scans for students who have left campus but haven't returned by their expected return date. 
                            This command only monitors and logs - it doesn't create actual debt records.
                        </p>
                        <div class="d-grid">
                            <button type="button" class="btn btn-warning btn-command" onclick="executeCommand('check-overdue')">
                                <span class="loading-spinner spinner-border spinner-border-sm me-2" role="status"></span>
                                <i class="fas fa-play me-2"></i>
                                Run Check Overdue
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Expire Overdue Command -->
            <div class="col-lg-4">
                <div class="card command-card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-danger bg-opacity-10 p-3 rounded-circle me-3">
                                <i class="fas fa-clock text-danger fs-4"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-1">Expire Overdue</h5>
                                <small class="text-muted">Cleanup Command</small>
                            </div>
                        </div>
                        <p class="command-description">
                            Automatically expires exeat requests that have passed their departure date + 6 hours 
                            and the student hasn't reached the security sign-in stage.
                        </p>
                        <div class="d-grid">
                            <button type="button" class="btn btn-danger btn-command" onclick="executeCommand('expire-overdue')">
                                <span class="loading-spinner spinner-border spinner-border-sm me-2" role="status"></span>
                                <i class="fas fa-play me-2"></i>
                                Run Expire Overdue
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Run All Commands -->
            <div class="col-lg-4">
                <div class="card command-card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-primary bg-opacity-10 p-3 rounded-circle me-3">
                                <i class="fas fa-rocket text-primary fs-4"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-1">Run All</h5>
                                <small class="text-muted">Batch Execution</small>
                            </div>
                        </div>
                        <p class="command-description">
                            Executes both commands in sequence: first checks for overdue exeats, 
                            then expires any that meet the criteria. Equivalent to the hourly scheduled run.
                        </p>
                        <div class="d-grid">
                            <button type="button" class="btn btn-primary btn-command" onclick="executeCommand('run-all')">
                                <span class="loading-spinner spinner-border spinner-border-sm me-2" role="status"></span>
                                <i class="fas fa-play me-2"></i>
                                Run All Commands
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Command Output Section -->
        <div class="row mt-4" id="output-section" style="display: none;">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-dark text-white">
                        <h6 class="mb-0">
                            <i class="fas fa-terminal me-2"></i>
                            Command Output
                            <button type="button" class="btn btn-sm btn-outline-light float-end" onclick="clearOutput()">
                                <i class="fas fa-trash me-1"></i>Clear
                            </button>
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="output-container p-3" id="command-output">
                            <!-- Command output will appear here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if(session('command_output'))
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0">
                                <i class="fas fa-terminal me-2"></i>
                                Last Command Output
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="output-container p-3">
                                <pre class="mb-0">{{ session('command_output') }}</pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Info Section -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card border-info">
                    <div class="card-header bg-info bg-opacity-10">
                        <h6 class="mb-0 text-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Important Information
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="fas fa-clock text-primary me-2"></i>Automatic Scheduling</h6>
                                <p class="small mb-3">These commands run automatically every hour via Laravel's task scheduler. Manual execution here is for immediate needs or testing purposes.</p>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-shield-alt text-success me-2"></i>Debt Creation</h6>
                                <p class="small mb-3">Actual student debt records are created when students sign in late through the security checkpoint, not by these monitoring commands.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // CSRF token setup for AJAX requests
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        function executeCommand(commandType) {
            const button = event.target.closest('button');
            const spinner = button.querySelector('.loading-spinner');
            const icon = button.querySelector('.fas.fa-play');
            const outputSection = document.getElementById('output-section');
            const outputContainer = document.getElementById('command-output');
            
            // Show loading state
            button.disabled = true;
            spinner.style.display = 'inline-block';
            icon.style.display = 'none';
            
            // Show output section
            outputSection.style.display = 'block';
            outputContainer.innerHTML = '<div class="text-info"><i class="fas fa-spinner fa-spin me-2"></i>Executing command...</div>';
            
            // Determine the endpoint
            let endpoint;
            switch(commandType) {
                case 'check-overdue':
                    endpoint = '/admin/commands/check-overdue';
                    break;
                case 'expire-overdue':
                    endpoint = '/admin/commands/expire-overdue';
                    break;
                case 'run-all':
                    endpoint = '/admin/commands/run-all';
                    break;
            }
            
            // Make AJAX request
            fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    outputContainer.innerHTML = `
                        <div class="text-success mb-2">
                            <i class="fas fa-check-circle me-2"></i>
                            ${data.message}
                        </div>
                        <pre class="mb-0">${data.output || data.combined_output || 'Command executed successfully'}</pre>
                    `;
                } else {
                    outputContainer.innerHTML = `
                        <div class="text-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            ${data.message}
                        </div>
                    `;
                }
            })
            .catch(error => {
                outputContainer.innerHTML = `
                    <div class="text-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Error: ${error.message}
                    </div>
                `;
            })
            .finally(() => {
                // Reset button state
                button.disabled = false;
                spinner.style.display = 'none';
                icon.style.display = 'inline';
            });
        }
        
        function clearOutput() {
            const outputSection = document.getElementById('output-section');
            const outputContainer = document.getElementById('command-output');
            outputContainer.innerHTML = '';
            outputSection.style.display = 'none';
        }
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>