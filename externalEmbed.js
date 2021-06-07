let embeds = document.getElementsByClassName("embed");
for (let i = 0; i < embeds.length; i++) {
    openDisclaimer(embeds[i]);
}


function openDisclaimer(element) {
    switch ([...element.classList].find(str => str.startsWith("embedType-")).substr(10)) {
        case "youtube_playlist":
        case "youtube_video":
        default:
            let jsonData = JSON.parse(element.attributes.getNamedItem("data-json").value);
            let openButton = '<button onclick="this.parentElement.outerHTML = renderIframe(this)">Open Embed</button>';
            element.innerHTML = jsonData.disclaimer + openButton;
            element.style.width = jsonData.width;
            element.style.height = jsonData.height;
            break;
    }
}

function renderIframe(button) {
    let jsonData = JSON.parse(button.parentElement.attributes.getNamedItem("data-json").value);
    return '<iframe style="border: none;" '
        + 'width="' + jsonData.width
        + '" height="' + jsonData.height
        + '" src="' + jsonData.request
        + '" muted="' + jsonData.muted
        + '" autoplay="' + jsonData.autoplay
        + '"></iframe>';
}
