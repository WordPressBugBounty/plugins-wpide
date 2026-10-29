/* WPIDE editor preview frame: live CSS and the visual selector picker. Printed inline for editors only. */
(function (w, d) {
  var cfg = w.wpideLivePreview || {};
  var parentWindow = w.parent && w.parent !== w ? w.parent : null;
  if (!parentWindow) {
    return;
  }

  function post(message) {
    message.source = 'wpide-preview';
    parentWindow.postMessage(message, cfg.origin);
  }

  w.addEventListener('message', function (event) {
    if (event.origin !== cfg.origin || !event.data || event.data.target !== 'wpide-preview') {
      return;
    }
    if (event.data.type === 'css') {
      var style = d.getElementById('wpide-live-css');
      if (!style) {
        style = d.createElement('style');
        style.id = 'wpide-live-css';
        (d.body || d.head).appendChild(style);
      }
      style.textContent = String(event.data.css || '');
    }
    if (event.data.type === 'highlight') {
      highlight(event.data.selector);
    }
  });

  // Selector picker: hover outline, click returns a short selector that matches the element.
  var box;

  function outline(el) {
    if (!box) {
      box = d.createElement('div');
      box.style.cssText = 'position:fixed;z-index:2147483647;pointer-events:none;border:2px solid #6576ff;background:rgba(101,118,255,.12);border-radius:2px;transition:all .06s ease-out;';
      d.documentElement.appendChild(box);
    }
    if (!el) {
      box.style.display = 'none';
      return;
    }
    var r = el.getBoundingClientRect();
    box.style.display = 'block';
    box.style.top = r.top + 'px';
    box.style.left = r.left + 'px';
    box.style.width = r.width + 'px';
    box.style.height = r.height + 'px';
  }

  function highlight(selector) {
    var el = null;
    try {
      el = selector ? d.querySelector(selector) : null;
    } catch (e) {
      el = null;
    }
    outline(el);
    if (el && el.scrollIntoView) {
      el.scrollIntoView({block: 'center', behavior: 'smooth'});
    }
    post({type: 'matches', count: el ? d.querySelectorAll(selector).length : 0});
  }

  function esc(value) {
    return w.CSS && w.CSS.escape ? w.CSS.escape(value) : String(value).replace(/([^a-zA-Z0-9_-])/g, '\\$1');
  }

  function unique(selector) {
    try {
      return d.querySelectorAll(selector).length === 1;
    } catch (e) {
      return false;
    }
  }

  // Classes that identify one post or only style it make brittle selectors.
  function usable(cls) {
    return !/\d/.test(cls) && !/^(has-|is-|align|wp-elements-|wp-container-)/.test(cls) && cls.indexOf(':') === -1;
  }

  function partFor(node) {
    if (node.id && !/\d{2,}/.test(node.id)) {
      return '#' + esc(node.id);
    }
    var part = node.tagName.toLowerCase();
    var classes = Array.prototype.filter.call(node.classList, usable).slice(0, 2);
    if (classes.length) {
      part += '.' + classes.map(esc).join('.');
    }
    var parent = node.parentNode;
    if (parent && parent.children) {
      var alike = Array.prototype.filter.call(parent.children, function (sibling) {
        return sibling.tagName === node.tagName && classes.every(function (c) {
          return sibling.classList.contains(c);
        });
      });
      if (alike.length > 1) {
        var sameTag = Array.prototype.filter.call(parent.children, function (sibling) {
          return sibling.tagName === node.tagName;
        });
        part += ':nth-of-type(' + (sameTag.indexOf(node) + 1) + ')';
      }
    }
    return part;
  }

  function selectorFor(el) {
    var parts = [];
    var node = el;
    while (node && node.nodeType === 1 && node !== d.documentElement && node !== d.body) {
      var part = partFor(node);
      parts.unshift(part);
      var candidate = parts.join(' > ');
      if (part.charAt(0) === '#' || unique(candidate)) {
        return candidate;
      }
      node = node.parentNode;
    }
    return parts.join(' > ');
  }

  if (cfg.mode === 'pick') {
    d.addEventListener('mouseover', function (event) {
      outline(event.target);
    }, true);
    d.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopPropagation();
      var selector = selectorFor(event.target);
      post({type: 'picked', selector: selector, count: d.querySelectorAll(selector).length, tag: event.target.tagName.toLowerCase()});
    }, true);
  }

  post({type: 'ready', mode: cfg.mode, url: w.location.href});
})(window, document);
