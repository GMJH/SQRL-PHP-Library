function sqrl_poll() {
  var elem = document.querySelector('.sqrl');
  if (elem !== null) {
    sqrl_load(sqrl.url.poll, function (response) {
      var result = JSON.parse(response);
      if (result.location !== undefined) {
        window.location = result.location;
      }
      else if (!result.stopPolling) {
        setTimeout(function () {sqrl_poll()}, sqrl.pollInterval);
      }
      else {
        document.querySelector('.sqrl').outerHTML = '<!-- SQRL removed, NUT no longer valid -->';
      }
    });
  }
}

function sqrl_load(url, fn) {
  var xmlhttp;
  if (window.XMLHttpRequest) {
    // code for IE7+, Firefox, Chrome, Opera, Safari
    xmlhttp = new XMLHttpRequest();
  } else {
    // code for IE6, IE5
    xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
  }
  xmlhttp.onreadystatechange = function() {
    if (xmlhttp.readyState == 4 ) {
      if(xmlhttp.status == 200){
        fn(xmlhttp.responseText);
      }
    }
  };
  xmlhttp.open("GET", url, true);
  xmlhttp.send();
}

document.addEventListener('DOMContentLoaded', function() {
  sqrl = sqrl || {};
  var cache = document.querySelector('#sqrl-cache');

  if (cache !== null) {
    cache.addEventListener('click', function (e) {
      var container = this;
      sqrl_load(sqrl.url.markup, function (response) {
        var result = JSON.parse(response);
        container.outerHTML = result.markup;
        sqrl = result.vars;
        setTimeout(function () {sqrl_poll()}, sqrl.pollIntervalInitial);
      });
      e.preventDefault();
    }, false);
  }
  setTimeout(function() {sqrl_poll()}, sqrl.pollIntervalInitial);

}, false);
