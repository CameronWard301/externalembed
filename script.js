let embeds = document.getElementsByClassName("externalembed_embed");
for (let i = 0; i < embeds.length; i++) {
    openDisclaimer(embeds[i]);
}


function openDisclaimer(element) {
    let jsonData = JSON.parse(element.attributes.getNamedItem("data-json").value);
    let openButton = '<button onclick="renderIframe(this);">Open Embed</button>';
    switch ([...element.classList].find(str => str.startsWith("embedType-")).substr(10)) {
        case "youtube_playlist":
        case "youtube_video":
            element.innerHTML = '<img style="z-index: 1;" src = "data:image/png;base64,' + jsonData.youtube_thumbnail +
                '" width="' + (jsonData.width == null ? 200 : jsonData.width) +
                '" height="' + (jsonData.height == null ? 600 : jsonData.height) +
                '"><div class="externalembed_disclaimer">' + jsonData.disclaimer + openButton + "</div>";
            element.style.width = jsonData.width + "px";
            element.style.height = jsonData.height + "px";
            break;
        default:
            element.innerHTML = jsonData.disclaimer + openButton;
            element.style.width = jsonData.width + "px";
            element.style.height = jsonData.height + "px";
            break;
    }
}

function renderIframe(button) {
    let jsonData = JSON.parse(button.parentElement.parentElement.attributes.getNamedItem("data-json").value);
    button.parentElement.parentElement.outerHTML = '<iframe style="border: none;" ' +
        'width="' + (jsonData.width == null ? 200 : jsonData.width + "px") +
        '" height="' + (jsonData.height == null ? 600 : jsonData.height + "px") +
        '" src="' + jsonData.request +
        '" ></iframe>';
}