  </main>
</div>
<script>
  (function () {
    var sidebar = document.querySelector('.sidebar');
    if (!sidebar) {
      return;
    }

    var collapseKey = 'hospitalHrSidebarCollapsed';
    var collapseToggles = document.querySelectorAll('[data-sidebar-toggle]');
    var isDesktop = function () {
      return !window.matchMedia || window.matchMedia('(min-width: 901px)').matches;
    };
    var applyCollapsedState = function (collapsed) {
      var enabled = isDesktop() && collapsed;
      document.body.classList.toggle('sidebar-collapsed', enabled);
      collapseToggles.forEach(function (toggle) {
        toggle.setAttribute('aria-label', enabled ? 'Expand navigation' : 'Collapse navigation');
        toggle.setAttribute('title', enabled ? 'Expand navigation' : 'Collapse navigation');
      });
    };
    applyCollapsedState(localStorage.getItem(collapseKey) === '1');
    collapseToggles.forEach(function (collapseToggle) {
      collapseToggle.addEventListener('click', function () {
        if (!isDesktop()) {
          document.body.classList.toggle('sidebar-menu-open');
          return;
        }
        var nextState = !document.body.classList.contains('sidebar-collapsed');
        localStorage.setItem(collapseKey, nextState ? '1' : '0');
        applyCollapsedState(nextState);
      });
    });
    window.addEventListener('resize', function () {
      applyCollapsedState(localStorage.getItem(collapseKey) === '1');
    });

    var key = 'hospitalHrSidebarScroll';
    var mobileNavigateKey = 'hospitalHrMobileNavigate';
    var saved = sessionStorage.getItem(key);
    if (saved !== null) {
      sidebar.scrollTop = parseInt(saved, 10) || 0;
    }

    var active = sidebar.querySelector('a.active');
    if (active) {
      var sidebarRect = sidebar.getBoundingClientRect();
      var activeRect = active.getBoundingClientRect();
      if (activeRect.top < sidebarRect.top || activeRect.bottom > sidebarRect.bottom) {
        active.scrollIntoView({block: 'nearest'});
      }
    }

    var currentRoute = new URLSearchParams(window.location.search).get('route') || 'dashboard';
    var routeAliases = {
      'report-detail': 'reports',
      'report-export': 'reports'
    };
    var activeRoute = routeAliases[currentRoute] || currentRoute;
    var updateActiveMenu = function () {
      var currentHash = window.location.hash || '';
      if (currentHash === '#previous-duty-roster') {
        var rosterDetails = document.getElementById('previous-duty-roster');
        if (rosterDetails) {
          rosterDetails.open = true;
        }
      }
      var bestMatch = null;
      sidebar.querySelectorAll('a[data-nav-route]').forEach(function (link) {
        var route = link.getAttribute('data-nav-route') || '';
        var hash = link.getAttribute('data-nav-hash') || '';
        var matches = route === activeRoute && hash === currentHash;
        if (!currentHash && route === activeRoute && !hash) {
          matches = true;
        }
        link.classList.toggle('active', matches);
        if (matches) {
          bestMatch = link;
        }
      });
      if (bestMatch) {
        bestMatch.scrollIntoView({block: 'nearest'});
      }
    };
    updateActiveMenu();
    window.addEventListener('hashchange', updateActiveMenu);

    sidebar.addEventListener('scroll', function () {
      sessionStorage.setItem(key, String(sidebar.scrollTop));
    }, {passive: true});

    sidebar.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () {
        sessionStorage.setItem(key, String(sidebar.scrollTop));
        if (!isDesktop()) {
          document.body.classList.remove('sidebar-menu-open');
        }
        if (window.matchMedia && window.matchMedia('(max-width: 900px)').matches && link.getAttribute('href')) {
          sessionStorage.setItem(mobileNavigateKey, '1');
        }
      });
    });

    // On mobile the navigation sits above the page. After a menu selection,
    // land directly on the selected page instead of leaving the user at the menu.
    if (window.matchMedia && window.matchMedia('(max-width: 900px)').matches && sessionStorage.getItem(mobileNavigateKey) === '1') {
      sessionStorage.removeItem(mobileNavigateKey);
      window.requestAnimationFrame(function () {
        var content = document.querySelector('.content');
        if (content) {
          content.scrollIntoView({block: 'start', inline: 'nearest'});
        }
      });
    }
  })();

  <?php if (function_exists('csrf_token')): ?>
  (function () {
    var csrfToken = <?= json_encode(csrf_token()) ?>;
    var ensureCsrf = function (form) {
      if (!form || String(form.method).toLowerCase() !== 'post') {
        return;
      }
      if (form.querySelector('input[name="_csrf"]')) {
        return;
      }
      var input = document.createElement('input');
      input.type = 'hidden';
      input.name = '_csrf';
      input.value = csrfToken;
      form.appendChild(input);
    };

    document.querySelectorAll('form').forEach(ensureCsrf);
    document.addEventListener('submit', function (event) {
      ensureCsrf(event.target);
    }, true);
  })();
  <?php endif; ?>

  (function () {
    var presets = {
      profile: {maxWidth: 256, maxHeight: 256, quality: 0.70, maxBytes: 450 * 1024},
      document: {maxWidth: 1600, maxHeight: 2200, quality: 0.78, maxBytes: 2 * 1024 * 1024}
    };

    var optimizeImageInput = function (input) {
      var preset = presets[input.dataset.optimizeImage || ''];
      if (!preset || !input.files || !input.files[0] || !window.File || !window.DataTransfer) {
        input._imageOptimizePromise = Promise.resolve();
        return input._imageOptimizePromise;
      }
      var file = input.files[0];
      if (file.size > 0 && file.size <= preset.maxBytes) {
        input._imageOptimizePromise = Promise.resolve();
        return input._imageOptimizePromise;
      }
      if (!file.type || file.type.indexOf('image/') !== 0 || file.type === 'image/svg+xml') {
        input._imageOptimizePromise = Promise.resolve();
        return input._imageOptimizePromise;
      }

      input._imageOptimizePromise = new Promise(function (resolve) {
        var reader = new FileReader();
        reader.onerror = resolve;
        reader.onload = function () {
          var image = new Image();
          image.onerror = resolve;
          image.onload = function () {
            var scale = Math.min(1, preset.maxWidth / image.width, preset.maxHeight / image.height);
            var width = Math.max(1, Math.round(image.width * scale));
            var height = Math.max(1, Math.round(image.height * scale));
            var canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            var context = canvas.getContext('2d');
            if (!context) {
              resolve();
              return;
            }
            context.drawImage(image, 0, 0, width, height);
            canvas.toBlob(function (blob) {
              if (!blob) {
                resolve();
                return;
              }
              if (blob.size > preset.maxBytes) {
                alert('Selected image is still too large after compression. Please choose a smaller image.');
                input.value = '';
                resolve();
                return;
              }
              var outputType = blob.type || 'image/webp';
              var outputExt = outputType === 'image/png' ? 'png' : (outputType === 'image/jpeg' ? 'jpg' : 'webp');
              var baseName = file.name.replace(/\.[^.]+$/, '') || 'upload';
              var optimizedFile = new File([blob], baseName + '.' + outputExt, {type: outputType, lastModified: Date.now()});
              var transfer = new DataTransfer();
              transfer.items.add(optimizedFile);
              input.files = transfer.files;
              resolve();
            }, 'image/webp', preset.quality);
          };
          image.src = String(reader.result || '');
        };
        reader.readAsDataURL(file);
      });
      return input._imageOptimizePromise;
    };

    document.querySelectorAll('input[type="file"][data-optimize-image]').forEach(function (input) {
      input.addEventListener('change', function () {
        optimizeImageInput(input);
      });
    });

    document.addEventListener('submit', function (event) {
      var form = event.target;
      if (!form || form.dataset.imageOptimizeSubmitting === '1') {
        return;
      }
      var inputs = Array.prototype.slice.call(form.querySelectorAll('input[type="file"][data-optimize-image]'));
      var pending = inputs
        .filter(function (input) { return input.files && input.files[0] && input.files[0].type.indexOf('image/') === 0; })
        .map(function (input) { return input._imageOptimizePromise || optimizeImageInput(input); });
      if (!pending.length) {
        return;
      }
      event.preventDefault();
      Promise.all(pending).then(function () {
        form.dataset.imageOptimizeSubmitting = '1';
        if (form.requestSubmit) {
          form.requestSubmit();
        } else {
          form.submit();
        }
        window.setTimeout(function () {
          delete form.dataset.imageOptimizeSubmitting;
        }, 1000);
      });
    }, true);
  })();

  (function () {
    var popups = document.querySelectorAll('.alert-popup');
    if (!popups.length) {
      return;
    }
    popups.forEach(function (popup, index) {
      window.setTimeout(function () {
        popup.classList.add('is-visible');
      }, 120 + (index * 90));
    });
    window.setTimeout(function () {
      popups.forEach(function (popup) {
        popup.classList.remove('is-visible');
      });
    }, 5000);
  })();

  (function () {
    var passwordWrap = document.querySelector('.top-password-wrap');
    var passwordToggle = document.querySelector('[data-password-toggle]');
    var passwordForm = document.querySelector('.top-password-form');
    if (!passwordWrap || !passwordToggle || !passwordForm) {
      return;
    }

    var closePasswordForm = function () {
      passwordForm.setAttribute('hidden', '');
      passwordToggle.setAttribute('aria-expanded', 'false');
    };

    passwordToggle.addEventListener('click', function (event) {
      event.stopPropagation();
      var willOpen = passwordForm.hasAttribute('hidden');
      if (willOpen) {
        passwordForm.removeAttribute('hidden');
        passwordToggle.setAttribute('aria-expanded', 'true');
        var firstInput = passwordForm.querySelector('input[type="password"]');
        if (firstInput) firstInput.focus();
        return;
      }
      closePasswordForm();
    });

    document.addEventListener('click', function (event) {
      if (!passwordForm.hasAttribute('hidden') && !passwordWrap.contains(event.target)) {
        closePasswordForm();
      }
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && !passwordForm.hasAttribute('hidden')) {
        closePasswordForm();
        passwordToggle.focus();
      }
    });
  })();

  (function () {
    try {
      localStorage.removeItem('hospitalHrTheme');
      document.documentElement.classList.remove('theme-dark');
    } catch (e) {}
  })();

  (function () {
    if (window.location.hash) {
      var target = document.querySelector(window.location.hash);
      if (target) {
        var parentDetails = target.closest('details');
        if (parentDetails) {
          parentDetails.open = true;
        }
      }
    }
  })();

  (function () {
    var forms = document.querySelectorAll('form[method="get"]:not([data-live-search="off"])');
    forms.forEach(function (form) {
      var timer = null;
      var submit = function (delay) {
        window.clearTimeout(timer);
        timer = window.setTimeout(function () {
          if (form.requestSubmit) {
            form.requestSubmit();
          } else {
            form.submit();
          }
        }, delay);
      };

      form.querySelectorAll('input, select').forEach(function (field) {
        if (!field.name || field.type === 'hidden' || field.disabled) {
          return;
        }

        if (['search', 'text'].includes(field.type) || field.tagName === 'TEXTAREA') {
          field.addEventListener('input', function () {
            submit(450);
          });
          return;
        }

        field.addEventListener('change', function () {
          submit(0);
        });
      });
    });
  })();

  (function () {
    var table = document.querySelector('.leave-approval-table');
    if (!table) {
      return;
    }

    var storageKey = 'hospitalHrLeaveColumnWidths';
    var headers = Array.prototype.slice.call(table.querySelectorAll('th'));
    var savedWidths = {};
    try {
      savedWidths = JSON.parse(localStorage.getItem(storageKey) || '{}') || {};
    } catch (error) {
      savedWidths = {};
    }

    var ensureColGroup = function () {
      var colgroup = table.querySelector('colgroup');
      if (!colgroup) {
        colgroup = document.createElement('colgroup');
        headers.forEach(function () {
          colgroup.appendChild(document.createElement('col'));
        });
        table.insertBefore(colgroup, table.firstChild);
      }
      return Array.prototype.slice.call(colgroup.children);
    };

    var columns = ensureColGroup();
    headers.forEach(function (header, index) {
      if (savedWidths[index]) {
        columns[index].style.width = savedWidths[index] + 'px';
      }
      header.classList.add('resizable-column');
      if (!header.querySelector('.column-resize-handle')) {
        var handle = document.createElement('span');
        handle.className = 'column-resize-handle';
        handle.setAttribute('aria-hidden', 'true');
        header.appendChild(handle);
      }
    });

    var persist = function () {
      var widths = {};
      columns.forEach(function (column, index) {
        var width = parseInt(column.style.width, 10);
        if (width) {
          widths[index] = width;
        }
      });
      localStorage.setItem(storageKey, JSON.stringify(widths));
    };

    headers.forEach(function (header, index) {
      var handle = header.querySelector('.column-resize-handle');
      if (!handle || !columns[index]) {
        return;
      }

      handle.addEventListener('dblclick', function (event) {
        event.preventDefault();
        columns.forEach(function (column) {
          column.style.width = '';
        });
        localStorage.removeItem(storageKey);
      });

      handle.addEventListener('pointerdown', function (event) {
        event.preventDefault();
        var startX = event.clientX;
        var startWidth = header.getBoundingClientRect().width;
        handle.setPointerCapture(event.pointerId);
        document.body.classList.add('is-resizing-column');

        var move = function (moveEvent) {
          var nextWidth = Math.max(42, Math.round(startWidth + moveEvent.clientX - startX));
          columns[index].style.width = nextWidth + 'px';
        };

        var stop = function () {
          document.body.classList.remove('is-resizing-column');
          persist();
          handle.removeEventListener('pointermove', move);
          handle.removeEventListener('pointerup', stop);
          handle.removeEventListener('pointercancel', stop);
        };

        handle.addEventListener('pointermove', move);
        handle.addEventListener('pointerup', stop);
        handle.addEventListener('pointercancel', stop);
      });
    });
  })();
</script>
<script>
  (function () {
    var trigger = document.querySelector('[data-profile-photo-open]');
    var modal = document.querySelector('[data-profile-photo-modal]');
    var preview = document.querySelector('[data-profile-photo-preview]');
    var close = document.querySelector('[data-profile-photo-close]');
    if (!trigger || !modal || !preview || !close) return;
    var hide = function () { modal.hidden = true; };
    trigger.addEventListener('click', function () {
      preview.src = trigger.getAttribute('data-profile-photo-open');
      modal.hidden = false;
    });
    close.addEventListener('click', hide);
    modal.addEventListener('click', function (event) { if (event.target === modal) hide(); });
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape') hide(); });
  })();
</script>
</body>
</html>
