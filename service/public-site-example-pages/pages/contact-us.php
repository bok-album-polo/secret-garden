<section>
    <h2>Contact Us</h2>
    <p>Let's grow something beautiful together.</p>

    <form action="" method="POST" enctype="multipart/form-data">
        <?=
        /** @var array<int,array<string,mixed> $fields */
        \App\Controllers\Controller::renderFields($fields)
        ?>

        <div>
            <button type="submit">Send Message</button>
            <br>
        </div>
    </form>
</section>