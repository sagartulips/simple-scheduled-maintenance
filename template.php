<!DOCTYPE html>
<html lang="<?php echo esc_attr($html_lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($heading); ?> | Maintenance Mode</title>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        html, body {
            width: 100%;
            height: 100%;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #ffffff;
            padding: 20px;
        }
        .maintenance-wrapper {
            width: 100%;
            max-width: 650px;
            margin: 0 auto;
        }
        .maintenance-container {
            width: 100%;
            text-align: center;
        }
        .maintenance-image {
            max-width: 180px;
            height: auto;
            margin: 0 auto 2rem;
            display: block;
        }
        .maintenance-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #000000;
            margin-bottom: 1.5rem;
        }
        .maintenance-description {
            font-size: 1.1rem;
            line-height: 1.8;
            color: #333333;
            margin-bottom: 2rem;
            word-wrap: break-word;
            overflow-wrap: break-word;
            font-family: inherit; /* Use normal font, not monospace */
            white-space: normal; /* Normal text wrapping */
        }
        .maintenance-description img {
            max-width: 100%;
            height: auto;
            margin: 1.5rem auto;
            display: block;
        }
        .maintenance-description p {
            margin: 15px 0;
        }
        /* Support alignment classes from WordPress editor */
        .maintenance-description .alignleft,
        .maintenance-description p.alignleft {
            text-align: left;
        }
        .maintenance-description .aligncenter,
        .maintenance-description p.aligncenter {
            text-align: center;
        }
        .maintenance-description .alignright,
        .maintenance-description p.alignright {
            text-align: right;
        }
        .maintenance-description .alignjustify,
        .maintenance-description p.alignjustify {
            text-align: justify;
        }
        .maintenance-description ul,
        .maintenance-description ol {
            margin: 15px 0;
            padding-left: 30px;
            text-align: left;
            display: inline-block;
        }
        .maintenance-description a {
            color: #0073aa;
            text-decoration: underline;
        }
        .maintenance-description a:hover {
            color: #005177;
        }
        /* Only style pre tags if they are explicitly added (for code snippets) */
        .maintenance-description pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            overflow-wrap: break-word;
            max-width: 100%;
            overflow-x: auto;
            padding: 1rem;
            background-color: #f5f5f5;
            border-radius: 0.25rem;
            margin: 1rem 0;
            font-family: "Courier New", Courier, monospace;
            font-size: 0.9em;
            line-height: 1.6;
        }
        /* Only style code tags if they are explicitly added (inline code) */
        .maintenance-description code {
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: break-word;
            background-color: #f5f5f5;
            padding: 0.2em 0.4em;
            border-radius: 0.2em;
            font-family: "Courier New", Courier, monospace;
            font-size: 0.9em;
        }
        .countdown-card {
            background-color: #f8f9fa;
            border-radius: 0.5rem;
            padding: 2rem;
            margin-top: 2rem;
        }
        .countdown-title {
            font-size: 0.9rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 1.5rem;
            color: #000000;
        }
        .countdown-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 1rem;
            justify-items: center;
        }
        .countdown-item {
            text-align: center;
        }
        .countdown-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #0d6efd;
            line-height: 1;
        }
        .countdown-label {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }
        @media (max-width: 768px) {
            body {
                padding: 15px;
            }
            .maintenance-wrapper {
                padding: 1.5rem;
            }
            .maintenance-title {
                font-size: 2rem;
            }
            .maintenance-description {
                font-size: 1rem;
            }
            .maintenance-description p {
                margin: 12px 0;
            }
            .maintenance-description pre {
                font-size: 0.85em;
                padding: 0.75rem;
                margin: 0.75rem 0;
                white-space: pre-wrap; /* Better for mobile */
            }
            .maintenance-image {
                max-width: 140px;
            }
            .countdown-value {
                font-size: 2rem;
            }
            .countdown-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
            }
            /* Force center alignment on mobile for better readability */
            .maintenance-description .alignleft,
            .maintenance-description .alignright,
            .maintenance-description p.alignleft,
            .maintenance-description p.alignright {
                text-align: center;
            }
        }
        @media (max-width: 480px) {
            body {
                padding: 10px;
            }
            .maintenance-wrapper {
                padding: 1rem;
            }
            .maintenance-title {
                font-size: 1.5rem;
            }
            .maintenance-description {
                font-size: 0.95rem;
            }
            .maintenance-description p {
                margin: 10px 0;
            }
            .maintenance-description ul,
            .maintenance-description ol {
                padding-left: 25px;
            }
            .maintenance-description pre {
                font-size: 0.8em;
                padding: 0.5rem;
                margin: 0.5rem 0;
                white-space: pre-wrap; /* Better for mobile - only if pre tag exists */
            }
            .maintenance-image {
                max-width: 120px;
            }
            .countdown-card {
                padding: 1.5rem;
            }
            .countdown-value {
                font-size: 1.75rem;
            }
            .countdown-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.5rem;
            }
            /* Force center alignment on small screens */
            .maintenance-description .alignleft,
            .maintenance-description .alignright,
            .maintenance-description p.alignleft,
            .maintenance-description p.alignright {
                text-align: center;
            }
        }
    </style>
    <?php if (empty($ssm_skip_wp_head)) { wp_head(); } ?>
</head>
<body>
    <div class="maintenance-wrapper">
        <div class="maintenance-container">
            <?php if ($show_image && !empty($image)): ?>
                <img src="<?php echo esc_url($image); ?>" alt="Maintenance" class="maintenance-image">
            <?php endif; ?>
            
            <h1 class="maintenance-title"><?php echo esc_html($heading); ?></h1>            
           
            <div class="maintenance-description">
                <?php echo $desc_formatted; ?>
            </div>
            
            <?php if ($show_countdown): ?>
                <div class="countdown-card">
                    <div class="countdown-title"><?php echo esc_html($countdown_text); ?></div>
                    <div class="countdown-grid" id="countdown">
                        <div class="countdown-item">
                            <div class="countdown-value" id="days">0</div>
                            <div class="countdown-label">Days</div>
                        </div>
                        <div class="countdown-item">
                            <div class="countdown-value" id="hours">0</div>
                            <div class="countdown-label">Hours</div>
                        </div>
                        <div class="countdown-item">
                            <div class="countdown-value" id="minutes">0</div>
                            <div class="countdown-label">Minutes</div>
                        </div>
                        <div class="countdown-item">
                            <div class="countdown-value" id="seconds">0</div>
                            <div class="countdown-label">Seconds</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($show_countdown): ?>
    <script>
        (function() {
            var endTime = <?php echo $countdown_end; ?>;
            
            function updateCountdown() {
                var now = Math.floor(Date.now() / 1000);
                var remaining = endTime - now;
                
                if (remaining <= 0) {
                    var daysEl = document.getElementById("days");
                    var hoursEl = document.getElementById("hours");
                    var minutesEl = document.getElementById("minutes");
                    var secondsEl = document.getElementById("seconds");
                    if (daysEl) daysEl.textContent = "0";
                    if (hoursEl) hoursEl.textContent = "0";
                    if (minutesEl) minutesEl.textContent = "0";
                    if (secondsEl) secondsEl.textContent = "0";
                    
                    // Auto-refresh the page when countdown ends
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                    return;
                }
                
                var days = Math.floor(remaining / 86400);
                var hours = Math.floor((remaining % 86400) / 3600);
                var minutes = Math.floor((remaining % 3600) / 60);
                var seconds = remaining % 60;
                
                var daysEl = document.getElementById("days");
                var hoursEl = document.getElementById("hours");
                var minutesEl = document.getElementById("minutes");
                var secondsEl = document.getElementById("seconds");
                if (daysEl) daysEl.textContent = days;
                if (hoursEl) hoursEl.textContent = hours;
                if (minutesEl) minutesEl.textContent = minutes;
                if (secondsEl) secondsEl.textContent = seconds;
            }
            
            updateCountdown();
            setInterval(updateCountdown, 1000);
        })();
    </script>
    <?php endif; ?>
    
    <?php if (empty($ssm_skip_wp_head)) { wp_footer(); } ?>
</body>
</html>

