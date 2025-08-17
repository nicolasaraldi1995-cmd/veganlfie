  </div> <!-- /main-content -->
  <script>
    // Active state by URL
    const path = location.pathname.split('/').pop();
    document.querySelectorAll('.menu-item').forEach(a=>{
      const href = a.getAttribute('href');
      if(href && href===path){ a.classList.add('active'); }
    });
  </script>
</body>
</html>
