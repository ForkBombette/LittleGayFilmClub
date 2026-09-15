<dialog id="movie-dialog" aria-labelledby="movie-dialog-title">
    <button type="button" id="close-movie-dialog" aria-label="Close film details">Close ×</button>
    <div id="movie-dialog-art" class="movie-art detail-art" aria-hidden="true"></div>
    <h2 id="movie-dialog-title"></h2>
    <p id="movie-dialog-meta"></p>
    <h3>The pitch</h3><p id="movie-dialog-pitch"></p>
    <div id="movie-dialog-synopsis"><h3>Synopsis</h3><p></p></div>
    <p id="movie-dialog-mystery">The identity stays hidden until the nominator deliberately reveals it, even if it wins.</p>
    <section class="movie-discussion" aria-labelledby="discussion-title">
        <h3 id="discussion-title">The discussion</h3>
        <p>One comment each, kept with the film across movie nights. Comments are public to the club; keep mystery spoilers out.</p>
        <div id="movie-comments"></div>
        <p id="comment-message" role="status"></p>
        <button type="button" id="refresh-comments">Refresh comments</button>
        <form id="comment-form" class="nomination-form">
            <label for="comment-body">Your comment</label>
            <textarea id="comment-body" rows="4" maxlength="2000" required disabled></textarea>
            <button id="save-comment" disabled>Save comment</button>
            <button type="button" id="delete-comment" hidden disabled>Delete my comment</button>
        </form>
    </section>
</dialog>
<script type="module" src="../frontend/dist/details-page.js"></script>
