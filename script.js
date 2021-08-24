function openDisclaimerAll() {
    let embeds = document.getElementsByClassName("externalembed_embed");
    for (let i = 0; i < embeds.length; i++) {
        openDisclaimer(embeds[i]);
    }
}

function openDisclaimer(element) {
    let jsonData = JSON.parse(element.attributes.getNamedItem("data-json").value);
    //let openButton = '<button onclick="renderIframe(this);">Open Embed</button>';
    if (localStorage.getItem("externalembed_tosaccepted_" + jsonData.domain) === "true") {
        element.classList.remove("externalembed_tosRejected_" + jsonData.domain);
        element.innerHTML = renderIframe(jsonData);

        //----------Modular----------
        /*switch ([...element.classList].find(str => str.startsWith("externalembed_embedType-")).substr("externalembed_embedType-".length)) {
            case "youtube_playlist":
            case "youtube_video":
                //element.parentElement.innerHTML = renderIframe(jsonData);
                element.innerHTML = generateThumbnail(jsonData) + '<div class="externalembed_disclaimer">' + jsonData.disclaimer + openButton + "</div>";

                //setSize(jsonData.width, jsonData.height, element);
                break;
            default:
                //setSize(jsonData.width, jsonData.height, element);
                element.innerHTML = jsonData.disclaimer + openButton;
                break;
        }*/
        //----------End Modular----------
    } else {
        if (localStorage.getItem("externalembed_tosaccepted_" + jsonData.domain) === "false") {
            let tosMessage = "<p>You have chosen not to view emedded content from: " + jsonData.domain + "</p><button class=\'externalembed_accept\' onclick=\'localStorage.removeItem(\"externalembed_tosaccepted_" + jsonData.domain + "\" );openDisclaimerAll()\'>View Terms</button>";
            element.innerHTML = "<div class='externalembed_disclaimer'>" + tosMessage + "</div>";
            element.classList.add("externalembed_tosRejected");
            element.style.width = "";
            element.style.height = "";
        } else {
            element.classList.remove("externalembed_tosRejected");
            element.style.height = jsonData.height;
            let tosMessage = "<p>" + jsonData.disclaimer + "</p><button class=\'externalembed_accept\' onclick=\'localStorage.setItem(\"externalembed_tosaccepted_" + jsonData.domain + "\",  \"true\");openDisclaimerAll()\'>Accept</button><button class=\'externalembed_reject\' onclick=\'localStorage.setItem(\"externalembed_tosaccepted_" + jsonData.domain + "\", \"false\");openDisclaimerAll()\'>Reject</button>";
            element.innerHTML = generateThumbnail(jsonData) + "<div class='externalembed_disclaimer'>" + tosMessage + "</div>";
            //setSize(jsonData.width, jsonData.height, element);
        }
    }
}

function renderIframe(jsonData) {
    //let jsonData = JSON.parse(button.parentElement.parentElement.attributes.getNamedItem("data-json").value);
    return '' +
        '<div class="' + jsonData.size + '">' +
        '<div class="externalembed_iframe_container">' +
        '<iframe style="border: none;" ' +
        'class="externalembed_iframe' +
        '" src="' + jsonData.request +
        '" ></iframe></div></div>';
}

function setSize(x, y, element) {
    element.style.width = x + "px";
    element.style.height = y + "px";
}

function generateThumbnail(json) {
    return '<img alt = "YouTube Thumbnail" src = "data:image/png;base64,' +
        json.thumbnail +
        //'" width="' + (json.width == null ? 200 : json.width) +
        // '" height="' + (json.height == null ? 600 : json.height) +
        '">'
}

openDisclaimerAll();
