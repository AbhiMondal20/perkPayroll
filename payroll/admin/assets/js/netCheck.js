function checkInternetSpeed() {
    if ('connection' in navigator) {
      const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
  
      // Check if the effective connection type indicates a slow connection
      if (['slow-2g', '2g', '3g'].includes(connection.effectiveType)) {
        alert('Your internet connection is slow. Some features may take longer to load.');
      }
  
      // Optional: Log details for debugging
      console.log(`Connection type: ${connection.effectiveType}`);
    } else {
      console.log('Network Information API is not supported in this browser.');
    }
  }
  
  // Run the check when the page loads
  window.addEventListener('load', checkInternetSpeed);
  