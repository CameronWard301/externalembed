let embeds = document.getElementsByClassName("embed");
for (let i = 0; i < embeds.length; i++) {
    openDisclaimer(embeds[i]);
}


function openDisclaimer(element) {
    switch ([...element.classList].find(str => str.startsWith("embedType-")).substr(10)) {
        case "youtube_playlist":
        case "youtube_video":
        default:
            let jsonData = JSON.parse(this.attributes.getNamedItem("data-json").value);
            let openButton = '<button onclick="this.parentElement.outerHTML = this.parentElement.attributes.getNamedItem(\'data-html\').value">Open Embed</button>';
            element.innerHTML = jsonData.disclaimer + openButton;
            break;
    }
}

function renderIframe(jsonData){
    return '<iframe style="border: none;" '
        + 'width="' + jsonData.width
        + '" height="' + jsonData.height
        + '" src="' + jsonData.request + '"></iframe>';
}
