function openDisclaimerAll() {
    let embeds = document.getElementsByClassName("externalembed_embed"); //get all the embeds on the page
    for (let i = 0; i < embeds.length; i++) {
        openDisclaimer(embeds[i]); //for every embed, process it
    }
}

function openDisclaimer(element) {
    let jsonData = JSON.parse(element.attributes.getNamedItem("data-json").value); //get the data passed from the server associated with the embed

    if (localStorage.getItem("externalembed_tosaccepted_" + jsonData.domain) === "true") { //if they have already accepted the TOS for the given domain:
        element.classList.remove("externalembed_tosRejected_" + jsonData.domain);
        element.innerHTML = renderIframe(jsonData); //render the embed and load content

    } else {
        if (localStorage.getItem("externalembed_tosaccepted_" + jsonData.domain) === "false") { //if the user has chosen to reject content from the domain, let them know and give them the option to re-view the terms:
            let tosMessage = "<p>You have chosen not to view emedded content from: " + jsonData.domain + "</p><button class=\'externalembed_accept\' onclick=\'localStorage.removeItem(\"externalembed_tosaccepted_" + jsonData.domain + "\" );openDisclaimerAll()\'>View Terms</button>";
            element.innerHTML = "<div class='externalembed_disclaimer externalembed_disclaimer_" + jsonData.type + "'>" + tosMessage + "</div>";
            element.classList.add("externalembed_tosRejected");
            element.style.width = "";
            element.style.height = "";
        } else { //the user hasn't said if they have accepted / rejected the embedded content
            element.classList.remove("externalembed_tosRejected");
            element.style.height = jsonData.height;
            let tosMessage = "<p>" + jsonData.disclaimer + "</p><button class=\'externalembed_accept\' onclick=\'localStorage.setItem(\"externalembed_tosaccepted_" + jsonData.domain + "\",  \"true\");openDisclaimerAll()\'>Accept</button><button class=\'externalembed_reject\' onclick=\'localStorage.setItem(\"externalembed_tosaccepted_" + jsonData.domain + "\", \"false\");openDisclaimerAll()\'>Reject</button>";
            if (jsonData.type !== 'other') {
                element.innerHTML = generateThumbnail(jsonData) + "<div class='externalembed_disclaimer externalembed_disclaimer_" + jsonData.type + "'>" + tosMessage + "</div>";
                //generate a thumbnail with the TOS accept and reject buttons
            } else {
                element.innerHTML = "<div class='externalembed_disclaimer externalembed_disclaimer_" + jsonData.type + "'>" + tosMessage + "</div>";
                //generate a disclaimer without a background image
            }

        }
    }
}

// Produce the iframe to load the embedded content using the data passed from the server
function renderIframe(jsonData) {
    if (jsonData.type === 'youtube_video' || jsonData.type === 'youtube_playlist') {
        return '' +
            '<div class="' + jsonData.size + '">' +
            '<div class="externalembed_iframe_container">' +
            '<iframe ' +
            'title="Embedded content from: ' + jsonData.domain +
            '" class="externalembed_iframe' +
            '" src="' + jsonData.request +
            '" ></iframe></div></div>';
    } else {
        return '' +
            '<div style="width: ' + jsonData.width + 'px; height: ' + jsonData.height + 'px;">' +
            '<div class="externalembed_iframe_container externalembed_other">' +
            '<iframe ' +
            'title="Embedded content from: ' + jsonData.domain +
            '" class="externalembed_iframe' +
            '" src="' + jsonData.request +
            '" ></iframe></div></div>';
    }
}

// Decode the thumbnail from the server and render it as an image
function generateThumbnail(json) {
    return '<img alt = "YouTube Thumbnail" src = "data:image/png;base64,' +
        json.thumbnail +
        '">'
}

openDisclaimerAll(); //run the script
