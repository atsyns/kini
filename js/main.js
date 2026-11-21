(() => {
  'use strict';

  const body = document.body;
  const sidebar = document.getElementById('sidebar');
  const sidebarToggle = document.getElementById('sidebarToggle');
  const sidebarOverlay = document.getElementById('sidebarOverlay');
  const navIndicator = document.getElementById('navIndicator');
  const progress = document.getElementById('navigationProgress');
  const mobileQuery = window.matchMedia('(max-width: 991.98px)');
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

  // ควบคุมสถานะย่อเมนู และเก็บสถานะใน localStorage อย่างปลอดภัย
  function readCollapsedState() {
    try {
      return localStorage.getItem('ttm-sidebar-collapsed') === 'true';
    } catch (error) {
      return false;
    }
  }
  function saveCollapsedState(collapsed) {
    try {
      localStorage.setItem('ttm-sidebar-collapsed', String(collapsed));
    } catch (error) {
      // ไม่ให้ข้อผิดพลาด localStorage ขัดขวางการใช้งานหน้า
    }
  }
  function setCollapsed(collapsed) {
    body.classList.toggle('sidebar-collapsed', collapsed);
    sidebarToggle.setAttribute('aria-expanded', String(!collapsed));
  }

  if (!mobileQuery.matches) setCollapsed(readCollapsedState());

  sidebarToggle.addEventListener('click', () => {
    if (mobileQuery.matches) {
      const open = !sidebar.classList.contains('mobile-open');
      sidebar.classList.toggle('mobile-open', open);
      sidebarOverlay.classList.toggle('open', open);
      sidebarOverlay.setAttribute('aria-hidden', String(!open));
      sidebarToggle.setAttribute('aria-expanded', String(open));
      return;
    }
    const collapsed = !body.classList.contains('sidebar-collapsed');
    setCollapsed(collapsed);
    saveCollapsedState(collapsed);
  });

  function closeMobileMenu() {
    sidebar.classList.remove('mobile-open');
    sidebarOverlay.classList.remove('open');
    sidebarOverlay.setAttribute('aria-hidden', 'true');
    sidebarToggle.setAttribute('aria-expanded', 'false');
  }
  sidebarOverlay.addEventListener('click', closeMobileMenu);
  mobileQuery.addEventListener('change', () => {
    closeMobileMenu();
    if (!mobileQuery.matches) setCollapsed(readCollapsedState());
  });

  // Accordion: เลื่อนความสูงอย่างลื่นและอัปเดต aria-expanded
  document.querySelectorAll('.nav-accordion').forEach((button) => {
    button.addEventListener('click', () => {
      const submenu = document.getElementById(button.getAttribute('aria-controls'));
      const expanded = button.getAttribute('aria-expanded') === 'true';
      button.setAttribute('aria-expanded', String(!expanded));
      submenu.classList.toggle('open', !expanded);
    });
  });

  // ตัวบ่งชี้เมนูเลื่อนอย่างนุ่มนวลไปยังรายการที่เปิดอยู่
  function moveNavIndicator() {
    if (body.classList.contains('sidebar-collapsed') && !mobileQuery.matches) {
      navIndicator.style.opacity = '0';
      return;
    }
    const active = document.querySelector('.nav-item.active');
    if (!active) return;
    const navRect = document.querySelector('.sidebar-nav').getBoundingClientRect();
    const itemRect = active.getBoundingClientRect();
    navIndicator.style.opacity = '1';
    navIndicator.style.transform = `translateY(${itemRect.top - navRect.top + document.querySelector('.sidebar-nav').scrollTop}px)`;
  }
  window.addEventListener('resize', moveNavIndicator);
  document.querySelector('.sidebar-nav').addEventListener('scroll', moveNavIndicator);
  requestAnimationFrame(moveNavIndicator);

  // Dropdown ผู้ใช้และปุ่มแจ้งเตือน: รองรับคลิกและปุ่ม Escape
  const userTrigger = document.getElementById('userMenuTrigger');
  const userDropdown = document.getElementById('userMenuDropdown');
  function closeUserMenu() {
    userDropdown.classList.remove('open');
    userTrigger.setAttribute('aria-expanded', 'false');
  }
  userTrigger.addEventListener('click', (event) => {
    event.stopPropagation();
    const open = !userDropdown.classList.contains('open');
    userDropdown.classList.toggle('open', open);
    userTrigger.setAttribute('aria-expanded', String(open));
  });
  document.addEventListener('click', (event) => {
    if (!event.target.closest('.user-menu')) closeUserMenu();
  });

  document.getElementById('notificationButton').addEventListener('click', (event) => {
    event.currentTarget.setAttribute('aria-expanded', 'true');
    const dot = event.currentTarget.querySelector('.notification-dot');
    if (dot) dot.style.display = 'none';
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeUserMenu();
      closeMobileMenu();
    }
  });

  // Staggered fade and rise ตอนโหลดหน้า
  const staggerItems = document.querySelectorAll('.stagger');
  staggerItems.forEach((item, index) => {
    if (!item.style.getPropertyValue('--stagger-index')) {
      item.style.setProperty('--stagger-index', String(index));
    }
  });
  requestAnimationFrame(() => {
    staggerItems.forEach((item) => item.classList.add('visible'));
  });

  // Count-up เริ่มเมื่อกราฟสถิติใกล้เข้ามาในหน้าจอ
  function animateCount(element) {
    if (element.dataset.animated === 'true') return;
    element.dataset.animated = 'true';
    const target = Number(element.dataset.target || 0);
    if (reducedMotion.matches) {
      element.textContent = target.toLocaleString('th-TH');
      return;
    }
    const duration = 900;
    const start = performance.now();
    function tick(now) {
      const progressValue = Math.min((now - start) / duration, 1);
      const eased = 1 - Math.pow(1 - progressValue, 3);
      element.textContent = Math.round(target * eased).toLocaleString('th-TH');
      if (progressValue < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  }
  const countObserver = new IntersectionObserver((entries, observer) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        animateCount(entry.target);
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.35 });
  document.querySelectorAll('.count-up').forEach((element) => countObserver.observe(element));

  // กราฟแท่งยืดขึ้นเมื่อโหลดหน้า
  window.addEventListener('load', () => {
    window.setTimeout(() => document.querySelector('.bar-chart')?.classList.add('loaded'), 180);
  });

  // Skeleton Loading สั้นๆ ก่อนแสดงตาราง
  window.addEventListener('load', () => {
    window.setTimeout(() => {
      document.getElementById('tableSkeleton')?.setAttribute('hidden', '');
      const tableContent = document.getElementById('tableContent');
      if (tableContent) tableContent.hidden = false;
    }, reducedMotion.matches ? 0 : 650);
  });

  // เอฟเฟกต์ระลอกคลื่นสำหรับปุ่มและลิงก์ที่เหมาะสม
  document.querySelectorAll('button, .nav-item, .view-all').forEach((element) => {
    element.addEventListener('click', function (event) {
      if (reducedMotion.matches) return;
      const rect = element.getBoundingClientRect();
      const diameter = Math.max(rect.width, rect.height);
      const ripple = document.createElement('span');
      ripple.className = 'ripple';
      ripple.style.width = ripple.style.height = `${diameter}px`;
      ripple.style.left = `${event.clientX - rect.left - diameter / 2}px`;
      ripple.style.top = `${event.clientY - rect.top - diameter / 2}px`;
      const position = getComputedStyle(element).position;
      if (position === 'static') element.style.position = 'relative';
      element.appendChild(ripple);
      ripple.addEventListener('animationend', () => ripple.remove());
    });
  });

  // แถบความคืบหน้าบางๆ แสดงชั่วคราวเมื่อเปลี่ยนหน้า
  document.querySelectorAll('a[href]').forEach((link) => {
    link.addEventListener('click', () => {
      const href = link.getAttribute('href');
      if (!href || href.startsWith('#') || link.target === '_blank') return;
      progress.style.opacity = '1';
      progress.style.width = '72%';
      window.setTimeout(() => {
        progress.style.width = '100%';
        window.setTimeout(() => {
          progress.style.opacity = '0';
          progress.style.width = '0';
        }, 180);
      }, 220);
    });
  });
})();
