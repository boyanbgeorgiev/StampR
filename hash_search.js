document.addEventListener("DOMContentLoaded", () => {
  const hashButton = document.getElementById("hashButton");
  const searchHashButton = document.getElementById("searchHashButton");
  const searchSerialButton = document.getElementById("searchSerialButton");
  const fileInput = document.getElementById("fileInput");
  const fileNameLabel = document.getElementById("fileName");

  if (!hashButton || !searchHashButton || !searchSerialButton || !fileInput || !fileNameLabel) {
    console.error("❌ Missing DOM elements.");
    return;
  }

  fileInput.addEventListener("change", () => {
    const file = fileInput.files[0];
    fileNameLabel.textContent = file ? file.name : "Няма избран";
  });

  hashButton.addEventListener("click", generateHash);
  searchHashButton.addEventListener("click", searchHash);
  searchSerialButton.addEventListener("click", searchSerial);
});


// Copy to clipboard
document.addEventListener("click", function (e) {
  if (e.target.classList.contains("copy-btn")) {
    const targetId = e.target.dataset.copy;
    const value = document.getElementById(targetId).textContent;
    navigator.clipboard.writeText(value).then(() => {
      e.target.textContent = "✅";
      setTimeout(() => e.target.textContent = "📋", 1500);
    });
  }
});

// 🔵 Central status update for the original box
function setOriginalStatus(msg, type = "success") {
  const originalStatus = document.getElementById("originalStatus");
  if (!originalStatus) return;
  originalStatus.textContent = msg;
  originalStatus.className = `status ${type}`;
}

function clearOriginalStatus() {
  const originalStatus = document.getElementById("originalStatus");
  if (!originalStatus) return;
  originalStatus.textContent = "";
  originalStatus.className = "status";
}

function generateHash() {
  const fileInput = document.getElementById("fileInput");
  const hashStatus = document.getElementById("hashStatus");

  if (!fileInput.files.length) {
    hashStatus.textContent = "❌ Моля, изберете файл.";
    hashStatus.className = "error";
    return;
  }

  const formData = new FormData();
  formData.append("file", fileInput.files[0]);

  hashStatus.className = "loading";

  fetch("hash_generate.php", {
    method: "POST",
    body: formData
  })
    .then(res => res.json())
    .then(data => {
      if (data.status === "success") {
        searchHash(data.file_hash);
        hashStatus.className = "success";
      } else {
        hashStatus.className = "error";
      }
    })
    .catch(err => {
      console.error("Fetch error:", err);
      hashStatus.textContent = "❌ Грешка при заявката към сървъра.";
      hashStatus.className = "error";
    });
}

function searchHash(fileHashParam) {
  const hashStatus = document.getElementById("hashStatus");
  const hashTable = document.getElementById("hashResultTable");
  const hashBody = document.getElementById("hashResultBody");

  const fileHash =
    typeof fileHashParam === "string" && fileHashParam.trim()
      ? fileHashParam.trim()
      : document.getElementById("hashInput")?.value.trim();

  if (!fileHash) {
    hashStatus.textContent = "❌ Моля, въведете валиден хеш.";
    hashStatus.className = "error";
    return;
  }

  hashStatus.textContent = "⏳ Търсене...";
  hashStatus.className = "loading";

  fetch(`hash_search.php?hash=${encodeURIComponent(fileHash)}`)
    .then(res => res.json())
    .then(data => {
      if (data.status === "found") {
        hashStatus.textContent = ""; // hide hash-specific message
        setOriginalStatus("✅ Данни намерени.");

        if (hashTable && hashBody) {
          hashTable.classList.remove("hidden");
          hashBody.innerHTML = `
            <tr>
              <td>${data.file_hash}</td>
              <td>${data.timestamp}</td>
              <td>${data.serial_number}</td>
            </tr>`;
        }

        updateStyledResult(data.timestamp, data.file_hash, data.serial_number);
      } else {
        hashStatus.textContent = `❌ ${data.message || "Хешът не е намерен."}`;
        hashStatus.className = "error";
        hashTable?.classList.add("hidden");
        clearOriginalStatus();
      }
    })
    .catch(err => {
      console.error("Search error:", err);
      hashStatus.textContent = "❌ Грешка при търсене.";
      hashStatus.className = "error";
      clearOriginalStatus();
    });
}

function searchSerial() {
  const serialInput = document.getElementById("serialInput");
  const serialStatus = document.getElementById("serialStatus");
  const serialTable = document.getElementById("serialResultTable");
  const serialBody = document.getElementById("serialResultBody");

  const serial = serialInput?.value.trim();

  if (!serial) {
    serialStatus.textContent = "❌ Моля, въведете сериен номер.";
    serialStatus.className = "error";
    return;
  }

  serialStatus.textContent = "⏳ Търсене...";
  serialStatus.className = "loading";

  fetch(`serial_search.php?serial=${encodeURIComponent(serial)}`)
    .then(res => res.json())
    .then(data => {
      if (data.status === "found") {
        serialStatus.textContent = ""; // hide serial-specific message
        setOriginalStatus("✅ Данни намерени.");

        if (serialTable && serialBody) {
          serialTable.classList.remove("hidden");
          serialBody.innerHTML = `
              <tr>
                <td>${data.serial_number}</td>
                <td>${data.file_hash}</td>
                <td>${data.timestamp}</td>
              </tr>`;
        }

        updateStyledResult(data.timestamp, data.file_hash, data.serial_number);
      } else {
        serialStatus.textContent = `❌ ${data.message || "Серийният номер не е намерен."}`;
        serialStatus.className = "error";
        serialTable?.classList.add("hidden");
        clearOriginalStatus();
      }
    })
    .catch(err => {
      console.error("Serial error:", err);
      serialStatus.textContent = "❌ Грешка при търсене.";
      serialStatus.className = "error";
      clearOriginalStatus();
    });
}

function updateStyledResult(timestamp, fileHash, serialNumber) {
  const resultCard = document.getElementById("timestampResult");
  const resultTimestamp = document.getElementById("resultTimestamp");
  const resultHash = document.getElementById("resultHash");
  const resultSerial = document.getElementById("resultSerial");

  if (!resultCard || !resultTimestamp || !resultHash || !resultSerial) {
    console.warn("❗ Result card elements missing.");
    return;
  }

  resultCard.classList.remove("hidden");
  resultTimestamp.textContent = timestamp || "—";
  resultHash.textContent = fileHash || "—";
  resultSerial.textContent = serialNumber || "—";
}
