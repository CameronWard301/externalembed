function openDisclaimerAll() {
    let embeds = document.getElementsByClassName("externalembed_embed");
    for (let i = 0; i < embeds.length; i++) {
        openDisclaimer(embeds[i]);
    }
}

function openDisclaimer(element) {
    let jsonData = JSON.parse(element.attributes.getNamedItem("data-json").value);
    let openButton = '<button onclick="renderIframe(this);">Open Embed</button>';
    if (localStorage.getItem("externalembed_tosaccepted") == "true") {
        //----------Modular----------
        switch ([...element.classList].find(str => str.startsWith("externalembed_embedType-")).substr("externalembed_embedType-".length)) {
            case "youtube_playlist":
            case "youtube_video":
                element.innerHTML = generateThumbnail(jsonData) + '<div class="externalembed_disclaimer">' + jsonData.disclaimer + openButton + "</div>";
                setSize(jsonData.width, jsonData.height, element);
                break;
            default:
                setSize(jsonData.width, jsonData.height, element);
                element.innerHTML = jsonData.disclaimer + openButton;
                break;
        }
        //----------End Modular----------
    } else {
        let tosMessage = "<p>tos accept question here. dont forget the buttons!</p><button onclick=\'localStorage.setItem(\"externalembed_tosaccepted\", \"true\");openDisclaimerAll()\'>Accept</button><button onclick=\'localStorage.setItem(\"externalembed_tosaccepted\", \"false\");openDisclaimerAll()\'>Denail tos</button>";
        if (localStorage.getItem("externalembed_tosaccepted") == "false"){
            tosMessage = "<p>tos denailed message here. dont forget the buttons!</p><button onclick=\'localStorage.setItem(\"externalembed_tosaccepted\", \"true\");openDisclaimerAll()\'>Re-Accept</button>";
        }
        element.innerHTML = generateThumbnail(jsonData) + "<div class='externalembed_disclaimer'>" + tosMessage + "</div>";
        setSize(jsonData.width, jsonData.height, element);
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

function setSize(x, y, element) {
    element.style.width = x + "px";
    element.style.height = y + "px";
}

function generateThumbnail(json){
    return '<img src = "data:image/png;base64,' + json.thumbnail +
        '" width="' + (json.width == null ? 200 : json.width) +
        '" height="' + (json.height == null ? 600 : json.height) +
        '">'
}

openDisclaimerAll();
