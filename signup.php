<?php
//signup.php
include 'connect.php';
include 'header.php';
 
echo '<h3>Sign up</h3>';
 
if($_SERVER['REQUEST_METHOD'] != 'POST')
{
    /*the form hasn't been posted yet, display it
      note that the action="" will cause the form to post to the same page it is on */
    echo '<form method="post" action="">
        Username: <input type="text" name="user_name" />
        Password: <input type="password" name="user_pass">
        Password again: <input type="password" name="user_pass_check">
        E-mail: <input type="email" name="user_email">
        <input type="submit" value="Add category" />
     </form>';
}
else
{
    /* so, the form has been posted, we'll process the data in three steps:
        1.  Check the data
        2.  Let the user refill the wrong fields (if necessary)
        3.  Save the data 
    */
    $errors = array(); /* declare the array for later use */
     
    if(isset($_POST['user_name']))
    {
        //the user name exists
        if(!ctype_alnum($_POST['user_name']))
        {
            $errors[] = 'The username can only contain letters and digits.';
        }
        if(strlen($_POST['user_name']) > 30)
        {
            $errors[] = 'The username cannot be longer than 30 characters.';
        }
    }
    else
    {
        $errors[] = 'The username field must not be empty.';
    }
     
     
    if(isset($_POST['user_pass']))
    {
        if($_POST['user_pass'] != $_POST['user_pass_check'])
        {
            $errors[] = 'The two passwords did not match.';
        }
    }
    else
    {
        $errors[] = 'The password field cannot be empty.';
    }
     
    if(!empty($errors)) /*check for an empty array, if there are errors, they're in this array (note the ! operator)*/
    {
        echo 'Uh-oh.. a couple of fields are not filled in correctly..';
        echo '<ul>';
        foreach($errors as $key => $value) /* walk through the array so all the errors get displayed */
        {
            echo '<li>' . $value . '</li>'; /* this generates a nice error list */
        }
        echo '</ul>';
    }
    else
    {
        //the form has been posted without, so save it
        //notice the use of mysql_real_escape_string, keep everything safe!
        //also notice the sha1 function which hashes the password
        $sql = "INSERT INTO
                    users(user_name, user_pass, user_email ,user_date, user_level)
                VALUES('" . mysql_real_escape_string($_POST['user_name']) . "',
                       '" . sha1($_POST['user_pass']) . "',
                       '" . mysql_real_escape_string($_POST['user_email']) . "',
                        NOW(),
                        0)";
                         
        $result = mysql_query($sql);
        if(!$result)
        {
            //something went wrong, display the error
            echo 'Something went wrong while registering. Please try again later.';
            //echo mysql_error(); //debugging purposes, uncomment when needed
        }
        else
        {
            echo 'Successfully registered. You can now <a href="signin.php">sign in</a> and start posting! :-)';
        }
    }
}
 
include 'footer.php';
?>

A lot of explanation is in the comments I made in the file, so be sure to check them out. The processing of the data takes place in three parts:

    Validating the data
    If the data is not valid, show the form again
    If the data is valid, save the record in the database

The PHP part is quite self-explanatory. The SQL-query however probably needs a little more explanation.
1
2
3
4
5
6
7
	
INSERT INTO
       users(user_name, user_pass, user_email ,user_date, user_level)
VALUES('" . mysql_real_escape_string($_POST['user_name']) . "',
       '" . sha1($_POST['user_pass']) . "',
       '" . mysql_real_escape_string($_POST['user_email']) . "',
       NOW(),   
       0);

On line 1 we have the INSERT INTO statement which speaks for itself. The table name is specified on the second line. The words between the brackets represent the columns in which we want to insert the data. The VALUES statement tells the database we're done declaring column names and it's time to specify the values. There is something new here: mysql_real_escape_string. The function escapes special characters in an unescaped string , so that it is safe to place it in a query. This function MUST always be used, with very few exceptions. There are too many scripts that don't use it and can be hacked real easy. Don't take the risk, use mysql_real_escape_string().

    "Never insert a plain password as-is. You MUST always encrypt it."

Also, you can see that the function sha1() is used to encrypt the user's password. This is also a very important thing to remember. Never insert a plain password as-is. You MUST always encrypt it. Imagine a hacker who somehow manages to get access to your database. If he sees all the plain-text passwords he could log into any (admin) account he wants. If the password columns contain sha1 strings he has to crack them first which is almost impossible.

Note: it's also possible to use md5(), I always use sha1() because benchmarks have proved it's a tiny bit faster, not much though. You can replace sha1 with md5 if you like.

If the signup process was successful, you should see something like this:

Try refreshing your phpMyAdmin screen, a new record should be visible in the users table.
Step 6: Adding Authentication and User Levels

An important aspect of a forum is the difference between regular users and admins/moderators. Since this is a small forum and adding features like adding new moderators and stuff would take way too much time, we'll focus on the login process and create some admin features like creating new categories and closing a thread.

Now that you've completed the previous step, we're going to make your freshly created account an admin account. In phpMyAdmin, click on the users table, and then 'Browse'. Your account will probably pop up right away. Click the edit icon and change the value of the user_level field from 0 to 1. That's it for now. You won't notice any difference in our application immediately, but when we've added the admin features a normal account and your account will have different capabilities.

The sign-in process works the following way:

    A visitor enters user data and submits the form
    If the username and password are correct, we can start a session
    If the username and password are incorrect, we show the form again with a message

The signin.php file is below. Don't think I'm not explaining what I'm doing, but check out the comments in the file. It's much easier to understand that way.
001
002
003
004
005
006
007
008
009
010
011
012
013
014
015
016
017
018
019
020
021
022
023
024
025
026
027
028
029
030
031
032
033
034
035
036
037
038
039
040
041
042
043
044
045
046
047
048
049
050
051
052
053
054
055
056
057
058
059
060
061
062
063
064
065
066
067
068
069
070
071
072
073
074
075
076
077
078
079
080
081
082
083
084
085
086
087
088
089
090
091
092
093
094
095
096
097
098
099
100
101
102
103
104
105
106
107
	
<?php
//signin.php
include 'connect.php';
include 'header.php';
 
echo '<h3>Sign in</h3>';
 
//first, check if the user is already signed in. If that is the case, there is no need to display this page
if(isset($_SESSION['signed_in']) && $_SESSION['signed_in'] == true)
{
    echo 'You are already signed in, you can <a href="signout.php">sign out</a> if you want.';
}
else
{
    if($_SERVER['REQUEST_METHOD'] != 'POST')
    {
        /*the form hasn't been posted yet, display it
          note that the action="" will cause the form to post to the same page it is on */
        echo '<form method="post" action="">
            Username: <input type="text" name="user_name" />
            Password: <input type="password" name="user_pass">
            <input type="submit" value="Sign in" />
         </form>';
    }
    else
    {
        /* so, the form has been posted, we'll process the data in three steps:
            1.  Check the data
            2.  Let the user refill the wrong fields (if necessary)
            3.  Varify if the data is correct and return the correct response
        */
        $errors = array(); /* declare the array for later use */
         
        if(!isset($_POST['user_name']))
        {
            $errors[] = 'The username field must not be empty.';
        }
         
        if(!isset($_POST['user_pass']))
        {
            $errors[] = 'The password field must not be empty.';
        }
         
        if(!empty($errors)) /*check for an empty array, if there are errors, they're in this array (note the ! operator)*/
        {
            echo 'Uh-oh.. a couple of fields are not filled in correctly..';
            echo '<ul>';
            foreach($errors as $key => $value) /* walk through the array so all the errors get displayed */
            {
                echo '<li>' . $value . '</li>'; /* this generates a nice error list */
            }
            echo '</ul>';
        }
        else
        {
            //the form has been posted without errors, so save it
            //notice the use of mysql_real_escape_string, keep everything safe!
            //also notice the sha1 function which hashes the password
            $sql = "SELECT 
                        user_id,
                        user_name,
                        user_level
                    FROM
                        users
                    WHERE
                        user_name = '" . mysql_real_escape_string($_POST['user_name']) . "'
                    AND
                        user_pass = '" . sha1($_POST['user_pass']) . "'";
                         
            $result = mysql_query($sql);
            if(!$result)
            {
                //something went wrong, display the error
                echo 'Something went wrong while signing in. Please try again later.';
                //echo mysql_error(); //debugging purposes, uncomment when needed
            }
            else
            {
                //the query was successfully executed, there are 2 possibilities
                //1. the query returned data, the user can be signed in
                //2. the query returned an empty result set, the credentials were wrong
                if(mysql_num_rows($result) == 0)
                {
                    echo 'You have supplied a wrong user/password combination. Please try again.';
                }
                else
                {
                    //set the $_SESSION['signed_in'] variable to TRUE
                    $_SESSION['signed_in'] = true;
                     
                    //we also put the user_id and user_name values in the $_SESSION, so we can use it at various pages
                    while($row = mysql_fetch_assoc($result))
                    {
                        $_SESSION['user_id']    = $row['user_id'];
                        $_SESSION['user_name']  = $row['user_name'];
                        $_SESSION['user_level'] = $row['user_level'];
                    }
                     
                    echo 'Welcome, ' . $_SESSION['user_name'] . '. <a href="index.php">Proceed to the forum overview</a>.';
                }
            }
        }
    }
}
 
include 'footer.php';
?>

This is the query that's in the signin.php file:
01
02
03
04
05
06
07
08
09
10
	
SELECT
    user_id,
    user_name,
    user_level
FROM
    users
WHERE
    user_name = '" . mysql_real_escape_string($_POST['user_name']) . "'
AND
    user_pass = '" . sha1($_POST['user_pass'])

It's obvious we need a check to tell if the supplied credentials belong to an existing user. A lot of scripts retrieve the password from the database and compare it using PHP. If we do this directly via SQL the password will be stored in the database once during registration and never leave it again. This is safer, because all the real action happens in the database layer and not in our application.

If the user is signed in successfully, we're doing a few things:
	
<?php
//set the $_SESSION['signed_in'] variable to TRUE
$_SESSION['signed_in'] = true;                  
//we also put the user_id and user_name values in the $_SESSION, so we can use it at various pages
while($row = mysql_fetch_assoc($result))
{
    $_SESSION['user_id'] = $row['user_id'];
    $_SESSION['user_name'] = $row['user_name']; 
}
?>

First, we set the 'signed_in' $_SESSION var to true, so we can use it on other pages to make sure the user is signed in. We also put the username and user id in the $_SESSION variable for usage on a different page. Finally, we display a link to the forum overview so the user can get started right away.

Of course signing in requires another function, signing out! The sign-out process is actually a lot easier than the sign-in process. Because all the information about the user is stored in $_SESSION variables, all we have to do is unset them and display a message.

Now that we've set the $_SESSION variables, we can determine if someone is signed in. Let's make a last simple change to header.php:

Replace:
	
<div id="userbar">Hello Example. Not you? Log out.</div>

<?php
<div id="userbar">
    if($_SESSION['signed_in'])
    {
        echo 'Hello' . $_SESSION['user_name'] . '. Not you? <a href="signout.php">Sign out</a>';
    }
    else
    {
        echo '<a href="signin.php">Sign in</a> or <a href="sign up">create an account</a>.';
    }
</div>

If a user is signed in, he will see his or her name displayed on the front page with a link to the signout page. Our authentication is done! By now our forum should look like this:
Step 7: Creating a Category

We want to create categories so let's start with making a form.
	
<form method="post" action="">
    Category name: <input type="text" name="cat_name" />
    Category description: <textarea name="cat_description" /></textarea>
    <input type="submit" value="Add category" />
 </form>

This step looks a lot like Step 4 (Signing up a user'), so I'm not going to do an in-depth explanation here. If you followed all the steps you should be able to understand this somewhat quickly.	
<?php
//create_cat.php
include 'connect.php';
 
if($_SERVER['REQUEST_METHOD'] != 'POST')
{
    //the form hasn't been posted yet, display it
    echo '<form method='post' action=''>
        Category name: <input type='text' name='cat_name' />
        Category description: <textarea name='cat_description' /></textarea>
        <input type='submit' value='Add category' />
     </form>';
}
else
{
    //the form has been posted, so save it
    $sql = ìINSERT INTO categories(cat_name, cat_description)
       VALUES('' . mysql_real_escape_string($_POST['cat_name']) . ì',
             '' . mysql_real_escape_string($_POST['cat_description']) . ì')';
    $result = mysql_query($sql);
    if(!$result)
    {
        //something went wrong, display the error
        echo 'Error' . mysql_error();
    }
    else
    {
        echo 'New category successfully added.';
    }
}
?>

As you can see, we've started the script with the $_SERVER check, after checking if the user has admin rights, which is required for creating a category. The form gets displayed if it hasn't been submitted already. If it has, the values are saved. Once again, a SQL query is prepared and then executed.
Step 8: Adding Categories to index.php

We've created some categories, so now we're able to display them on the front page. Let's add the following query to the content area of index.php.

	
SELECT
    categories.cat_id,
    categories.cat_name,
    categories.cat_description,
FROM
    categories

This query selects all categories and their names and descriptions from the categories table. We only need a bit of PHP to display the results. If we add that part just like we did in the previous steps, the code will look like this.	
<?php
//create_cat.php
include 'connect.php';
include 'header.php';
 
$sql = "SELECT
            cat_id,
            cat_name,
            cat_description,
        FROM
            categories";
 
$result = mysql_query($sql);
 
if(!$result)
{
    echo 'The categories could not be displayed, please try again later.';
}
else
{
    if(mysql_num_rows($result) == 0)
    {
        echo 'No categories defined yet.';
    }
    else
    {
        //prepare the table
        echo '<table border="1">
              <tr>
                <th>Category</th>
                <th>Last topic</th>
              </tr>'; 
             
        while($row = mysql_fetch_assoc($result))
        {               
            echo '<tr>';
                echo '<td class="leftpart">';
                    echo '<h3><a href="category.php?id">' . $row['cat_name'] . '</a></h3>' . $row['cat_description'];
                echo '</td>';
                echo '<td class="rightpart">';
                            echo '<a href="topic.php?id=">Topic subject</a> at 10-10';
                echo '</td>';
            echo '</tr>';
        }
    }
}
 
include 'footer.php';
?>

Notice how we're using the cat_id to create links to category.php. All the links to this page will look like this: category.php?cat_id=x, where x can be any numeric value. This may be new to you. We can check the url with PHP for $_GET values. For example, we have this link:
1
	
category.php?cat_id=23

The statement echo $_GET[ëcat_id'];' will display '23'. In the next few steps we'll use this value to retrieve the topics when viewing a single category, but topics can't be viewed if we haven't created them yet. So let's create some topics!
Step 9: Creating a Topic

In this step, we're combining the techniques we learned in the previous steps. We're checking if a user is signed in, we'll use an input query to create the topic and create some basic HTML forms.

The structure of create_topic.php can hardly be explained in a list or something, so I rewrote it in pseudo-code.

<?php
if(user is signed in)
{
    //the user is not signed in
}
else
{
    //the user is signed in
    if(form has not been posted)
    {   
        //show form
    }
    else
    {
        //process form
    }
}
?>

Here's the real code of this part of our forum, check the explanations below the code to see what it's doing.
<?php
//create_cat.php
include 'connect.php';
include 'header.php';
 
echo '<h2>Create a topic</h2>';
if($_SESSION['signed_in'] == false)
{
    //the user is not signed in
    echo 'Sorry, you have to be <a href="/forum/signin.php">signed in</a> to create a topic.';
}
else
{
    //the user is signed in
    if($_SERVER['REQUEST_METHOD'] != 'POST')
    {   
        //the form hasn't been posted yet, display it
        //retrieve the categories from the database for use in the dropdown
        $sql = "SELECT
                    cat_id,
                    cat_name,
                    cat_description
                FROM
                    categories";
         
        $result = mysql_query($sql);
         
        if(!$result)
        {
            //the query failed, uh-oh :-(
            echo 'Error while selecting from database. Please try again later.';
        }
        else
        {
            if(mysql_num_rows($result) == 0)
            {
                //there are no categories, so a topic can't be posted
                if($_SESSION['user_level'] == 1)
                {
                    echo 'You have not created categories yet.';
                }
                else
                {
                    echo 'Before you can post a topic, you must wait for an admin to create some categories.';
                }
            }
            else
            {
         
                echo '<form method="post" action="">
                    Subject: <input type="text" name="topic_subject" />
                    Category:'; 
                 
                echo '<select name="topic_cat">';
                    while($row = mysql_fetch_assoc($result))
                    {
                        echo '<option value="' . $row['cat_id'] . '">' . $row['cat_name'] . '</option>';
                    }
                echo '</select>'; 
                     
                echo 'Message: <textarea name="post_content" /></textarea>
                    <input type="submit" value="Create topic" />
                 </form>';
            }
        }
    }
    else
    {
        //start the transaction
        $query  = "BEGIN WORK;";
        $result = mysql_query($query);
         
        if(!$result)
        {
            //Damn! the query failed, quit
            echo 'An error occured while creating your topic. Please try again later.';
        }
        else
        {
     
            //the form has been posted, so save it
            //insert the topic into the topics table first, then we'll save the post into the posts table
            $sql = "INSERT INTO 
                        topics(topic_subject,
                               topic_date,
                               topic_cat,
                               topic_by)
                   VALUES('" . mysql_real_escape_string($_POST['topic_subject']) . "',
                               NOW(),
                               " . mysql_real_escape_string($_POST['topic_cat']) . ",
                               " . $_SESSION['user_id'] . "
                               )";
                      
            $result = mysql_query($sql);
            if(!$result)
            {
                //something went wrong, display the error
                echo 'An error occured while inserting your data. Please try again later.' . mysql_error();
                $sql = "ROLLBACK;";
                $result = mysql_query($sql);
            }
            else
            {
                //the first query worked, now start the second, posts query
                //retrieve the id of the freshly created topic for usage in the posts query
                $topicid = mysql_insert_id();
                 
                $sql = "INSERT INTO
                            posts(post_content,
                                  post_date,
                                  post_topic,
                                  post_by)
                        VALUES
                            ('" . mysql_real_escape_string($_POST['post_content']) . "',
                                  NOW(),
                                  " . $topicid . ",
                                  " . $_SESSION['user_id'] . "
                            )";
                $result = mysql_query($sql);
                 
                if(!$result)
                {
                    //something went wrong, display the error
                    echo 'An error occured while inserting your post. Please try again later.' . mysql_error();
                    $sql = "ROLLBACK;";
                    $result = mysql_query($sql);
                }
                else
                {
                    $sql = "COMMIT;";
                    $result = mysql_query($sql);
                     
                    //after a lot of work, the query succeeded!
                    echo 'You have successfully created <a href="topic.php?id='. $topicid . '">your new topic</a>.';
                }
            }
        }
    }
}
 
include 'footer.php';
?>
	
<?php
//start the transaction
$query  = "BEGIN WORK;";
$result = mysql_query($query);
//stop the transaction
$sql = "ROLLBACK;";
$result = mysql_query($sql);
//commit the transaction
$sql = "COMMIT;";
$result = mysql_query($sql);
?>

	
<?php
//create_cat.php
include 'connect.php';
include 'header.php';
 
//first select the category based on $_GET['cat_id']
$sql = "SELECT
            cat_id,
            cat_name,
            cat_description
        FROM
            categories
        WHERE
            cat_id = " . mysql_real_escape_string($_GET['id']);
 
$result = mysql_query($sql);
 
if(!$result)
{
    echo 'The category could not be displayed, please try again later.' . mysql_error();
}
else
{
    if(mysql_num_rows($result) == 0)
    {
        echo 'This category does not exist.';
    }
    else
    {
        //display category data
        while($row = mysql_fetch_assoc($result))
        {
            echo '<h2>Topics in ′' . $row['cat_name'] . '′ category</h2>';
        }
     
        //do a query for the topics
        $sql = "SELECT  
                    topic_id,
                    topic_subject,
                    topic_date,
                    topic_cat
                FROM
                    topics
                WHERE
                    topic_cat = " . mysql_real_escape_string($_GET['id']);
         
        $result = mysql_query($sql);
         
        if(!$result)
        {
            echo 'The topics could not be displayed, please try again later.';
        }
        else
        {
            if(mysql_num_rows($result) == 0)
            {
                echo 'There are no topics in this category yet.';
            }
            else
            {
                //prepare the table
                echo '<table border="1">
                      <tr>
                        <th>Topic</th>
                        <th>Created at</th>
                      </tr>'; 
                     
                while($row = mysql_fetch_assoc($result))
                {               
                    echo '<tr>';
                        echo '<td class="leftpart">';
                            echo '<h3><a href="topic.php?id=' . $row['topic_id'] . '">' . $row['topic_subject'] . '</a><h3>';
                        echo '</td>';
                        echo '<td class="rightpart">';
                            echo date('d-m-Y', strtotime($row['topic_date']));
                        echo '</td>';
                    echo '</tr>';
                }
            }
        }
    }
}
 
include 'footer.php';
?>
	
<form method="post" action="reply.php?id=5">
    <textarea name="reply-content"></textarea>
    <input type="submit" value="Submit reply" />
</form>
	
<?php
//create_cat.php
include 'connect.php';
include 'header.php';
 
if($_SERVER['REQUEST_METHOD'] != 'POST')
{
    //someone is calling the file directly, which we don't want
    echo 'This file cannot be called directly.';
}
else
{
    //check for sign in status
    if(!$_SESSION['signed_in'])
    {
        echo 'You must be signed in to post a reply.';
    }
    else
    {
        //a real user posted a real reply
        $sql = "INSERT INTO 
                    posts(post_content,
                          post_date,
                          post_topic,
                          post_by) 
                VALUES ('" . $_POST['reply-content'] . "',
                        NOW(),
                        " . mysql_real_escape_string($_GET['id']) . ",
                        " . $_SESSION['user_id'] . ")";
                         
        $result = mysql_query($sql);
                         
        if(!$result)
        {
            echo 'Your reply has not been saved, please try again later.';
        }
        else
        {
            echo 'Your reply has been saved, check out <a href="topic.php?id=' . htmlentities($_GET['id']) . '">the topic</a>.';
        }
    }
}
 
include 'footer.php';
?>
