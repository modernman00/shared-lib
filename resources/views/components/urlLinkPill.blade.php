{{--
  Sovereign URL Link Pill Component
  Replaces raw URLs and messy query parameters with a clean, tactile Link Icon (🔗) + Relevant Action Title.
  Hides the full URL from plain sight while providing direct navigation and 1-tap copy functionality.
  Adheres to Global Design Standards (v2.1) and BRATS/Red Team Security Shield.

  Usage:
  @include('components.urlLinkPill', [
      'url'      => $shareUrl,
      'title'    => 'Enter Private Family Sanctuary',
      'subtitle' => 'myfamilyplatform.com • 100% Private',
      'icon'     => '🔗',
      'id'       => 'sanctuarySharePill'
  ])
--}}

@php
    $pillUrl = trim((string)($url ?? '#'));
    
    // Security Shield (Marcus & Dr. Viktor Kovács Red Team Guardrail):
    // Defend against javascript:, data:, and malicious protocol exploits
    $disallowedProtocols = ['javascript:', 'data:', 'vbscript:', 'file:'];
    $loweredUrl = strtolower($pillUrl);
    foreach ($disallowedProtocols as $badProto) {
        if (str_starts_with($loweredUrl, $badProto)) {
            $pillUrl = '#blocked-unsafe-url';
            break;
        }
    }

    $pillTitle    = htmlspecialchars(trim((string)($title ?? 'Open Private Link')), ENT_QUOTES, 'UTF-8');
    $pillSubtitle = !empty($subtitle) ? htmlspecialchars(trim((string)$subtitle), ENT_QUOTES, 'UTF-8') : '';
    $pillIcon     = !empty($icon) ? htmlspecialchars(trim((string)$icon), ENT_QUOTES, 'UTF-8') : '🔗';
    $pillId       = !empty($id) ? preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$id) : 'urlPill_' . substr(md5($pillUrl . mt_rand()), 0, 8);
    $showCopy     = !isset($showCopy) || (bool)$showCopy;
    $target       = !empty($target) ? htmlspecialchars((string)$target, ENT_QUOTES, 'UTF-8') : '_blank';
@endphp

<div class="sanctuary-url-pill-wrapper my-2" id="{{ $pillId }}_wrap">
    <div class="sanctuary-url-pill d-flex align-items-center justify-content-between p-2 rounded-3"
         style="background: #fdf2f2; border: 1.5px solid rgba(163, 22, 33, 0.22); box-shadow: 0 2px 8px rgba(163, 22, 33, 0.06); transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);">
        
        <!-- Clickable Action Area: Hides full URL behind Link Icon + Title -->
        <a href="{{ $pillUrl }}" 
           target="{{ $target }}" 
           rel="noopener noreferrer"
           class="d-flex align-items-center gap-2.5 text-decoration-none text-dark flex-grow-1 min-w-0 px-2 py-1"
           style="min-height: 44px; touch-action: manipulation; outline: none;"
           title="{{ $pillTitle }}">
            
            <!-- Link Icon -->
            <div class="sanctuary-link-icon-badge flex-shrink-0 d-flex align-items-center justify-content-center rounded-circle"
                 style="width: 38px; height: 38px; background: rgba(163, 22, 33, 0.12); color: #8a1019; font-size: 1.25rem;">
                <span aria-hidden="true">{{ $pillIcon }}</span>
            </div>

            <!-- Relevant Title & Clean Subtitle (URL is hidden) -->
            <div class="d-flex flex-column min-w-0 me-2">
                <span class="fw-bold text-dark text-truncate" style="font-size: 0.92rem; letter-spacing: -0.01em; line-height: 1.25;">
                    {{ $pillTitle }}
                </span>
                @if($pillSubtitle !== '')
                    <span class="text-muted small text-truncate" style="font-size: 0.76rem; font-weight: 500;">
                        {{ $pillSubtitle }}
                    </span>
                @endif
            </div>

            <!-- External Arrow -->
            <i class="bi bi-arrow-up-right-circle text-primary ms-auto me-1 opacity-75" style="font-size: 1.05rem;" aria-hidden="true"></i>
        </a>

        @if($showCopy)
            <!-- 1-Tap Copy Button -->
            <button type="button" 
                    id="{{ $pillId }}_copyBtn"
                    data-copy-url="{{ $pillUrl }}"
                    class="btn btn-sm btn-outline-primary d-inline-flex align-items-center justify-content-center gap-1.5 px-3 rounded-pill fw-semibold shadow-xs flex-shrink-0 ms-1"
                    style="min-height: 44px; min-width: 44px; font-size: 0.82rem; white-space: nowrap; border-color: rgba(163, 22, 33, 0.35); color: #8a1019;"
                    aria-label="Copy link to clipboard">
                <i class="bi bi-clipboard" id="{{ $pillId }}_copyIcon"></i>
                <span id="{{ $pillId }}_copyText">Copy</span>
            </button>
        @endif

    </div>
    
    <!-- Hidden Accessible Raw URL Container (Preserved for assistive tech and copy engines) -->
    <span class="visually-hidden" id="{{ $pillId }}_raw" aria-hidden="true">{{ $pillUrl }}</span>
</div>

<script nonce="{{ $nonce ?? '' }}">
(function() {
    var copyBtn = document.getElementById('{{ $pillId }}_copyBtn');
    if (!copyBtn) return;

    copyBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();

        var urlToCopy = this.getAttribute('data-copy-url') || '';
        if (!urlToCopy || urlToCopy === '#') return;

        var textSpan = document.getElementById('{{ $pillId }}_copyText');
        var iconEl   = document.getElementById('{{ $pillId }}_copyIcon');

        function setCopiedState() {
            if (textSpan) textSpan.textContent = 'Copied! ✓';
            if (iconEl) {
                iconEl.className = 'bi bi-check-lg text-success';
            }
            copyBtn.classList.remove('btn-outline-primary');
            copyBtn.classList.add('btn-success');
            copyBtn.style.color = '#ffffff';

            setTimeout(function() {
                if (textSpan) textSpan.textContent = 'Copy';
                if (iconEl) {
                    iconEl.className = 'bi bi-clipboard';
                }
                copyBtn.classList.remove('btn-success');
                copyBtn.classList.add('btn-outline-primary');
                copyBtn.style.color = '#8a1019';
            }, 2500);
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(urlToCopy).then(setCopiedState).catch(function() {
                fallbackCopy(urlToCopy);
            });
        } else {
            fallbackCopy(urlToCopy);
        }

        function fallbackCopy(text) {
            try {
                var ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'fixed';
                ta.style.left = '-9999px';
                document.body.appendChild(ta);
                ta.focus();
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                setCopiedState();
            } catch (err) {
                prompt('Copy link:', text);
            }
        }
    });
})();
</script>
