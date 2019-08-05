<html>
  <head>
    <title>WebUSB Serial Sample Application</title>
  </head>


<body>
  <script>
var serial = {};
(function() {
  'use strict';
  serial.getPorts = function() {
    return navigator.usb.getDevices().then(devices => {
      return devices.map(device => new serial.Port(device));
    });
  };
  serial.requestPort = function() {
    const filters = [
      { 'vendorId': 0xDEAD, 'productId': 0xBEEF },
      { 'vendorId': 0x8086, 'productId': 0xF8A1 },
    ];
    return navigator.usb.requestDevice({ 'filters': filters }).then(
      device => new serial.Port(device)
    );
  }
  serial.Port = function(device) {
    this.device_ = device;
  };
  serial.Port.prototype.connect = function() {
    let readLoop = () => {
      this.device_.transferIn(3, 64).then(result => {
        this.onReceive(result.data);
        readLoop();
      }, error => {
        this.onReceiveError(error);
      });
    };
    return this.device_.open()
        .then(() => {
          if (this.device_.configuration === null) {
            return this.device_.selectConfiguration(1);
          }
        })
        .then(() => this.device_.claimInterface(2))
        .then(() => this.device_.controlTransferOut({
            'requestType': 'class',
            'recipient': 'interface',
            'request': 0x22,
            'value': 0x01,
            'index': 0x02}))
        .then(() => {
          readLoop();
        });
  };
  serial.Port.prototype.disconnect = function() {
    return this.device_.controlTransferOut({
            'requestType': 'class',
            'recipient': 'interface',
            'request': 0x22,
            'value': 0x00,
            'index': 0x02})
        .then(() => this.device_.close());
  };
  serial.Port.prototype.send = function(data) {
    return this.device_.transferOut(2, data);
  };
})();
let port;
function connect() {
  port.connect().then(() => {
    port.onReceive = data => {
      let textDecoder = new TextDecoder();
      console.log("Received:", textDecoder.decode(data));
      document.getElementById('output').value += textDecoder.decode(data);
    }
    port.onReceiveError = error => {
      console.error(error);
      document.querySelector("#connect").style = "visibility: initial";
      port.disconnect();
    };
  });
}
function send(string) {
  console.log("sending to serial:" + string.length);
  if (string.length === 0)
    return;
  console.log("sending to serial: [" + string +"]\n");
  let view = new TextEncoder('utf-8').encode(string);
  console.log(view);
  if (port) {
    port.send(view);
  }
};
window.onload = _ => {
  document.querySelector("#connect").onclick = function() {
    serial.requestPort().then(selectedPort => {
      port = selectedPort;
      this.style = "visibility: hidden";
      connect();
    });
  }
  document.querySelector("#submit").onclick = () => {
    let source = document.querySelector("#editor").value;
    send(source);
  }
}
</script>
 <button id="connect" style="visibility: initial">Connect To WebUSB Device</button>
 <br><br><label for="title">Sender: </label> <br>
 <textarea id="editor", rows="25" cols="80" id="source">WebUSB!</textarea>
 <br><button id="submit">Send</button>
 <br><br>
 <label for="title">Receiver: </label> </br>
 <textarea id="output", rows="25" cols="80" id="source"></textarea>
</body>
