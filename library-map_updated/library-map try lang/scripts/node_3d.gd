extends Node3D

@onready var camera_3d: Camera3D = $Camera3D
@onready var top_down_camera: Camera3D = $top_down_camera
@onready var label: Label = $Camera3D/Label
@onready var genre: Label = $Camera3D/genre

@onready var genre_bg: Sprite2D = $Camera3D/genre_bg
@onready var genre_selected: Sprite2D = $Camera3D/genre_selected

@onready var section_1_cam: Camera3D = $section1_cam
@onready var arrow: Node3D = $arrow

@onready var sprite_2d: Sprite2D = $Camera3D/Sprite2D
@onready var hide: Button = $Camera3D/hide
@onready var show: Button = $Camera3D/show

@onready var philo_marker: Sprite3D = $Philosophy/philo_marker
@onready var psych_marker: Sprite3D = $Psychology/psych_marker
@onready var educ_marker: Sprite3D = $Education/educ_marker

@onready var psychology: Area3D = $Psychology
@onready var philosophy: Area3D = $Philosophy
@onready var sec_1_highlight: Sprite3D = $sec1_highlight

@onready var science: Area3D = $Science
@onready var collision_shape_3d: CollisionShape3D = $Science/CollisionShape3D
@onready var sci_marker: Sprite3D = $Science/sci_marker

@onready var english: Area3D = $English
@onready var eng_marker: Sprite3D = $English/eng_marker

@onready var law: Area3D = $Law
@onready var law_marker: Sprite3D = $Law/law_marker


# BOOK ARRAYS

var philosophy_books = [
	"Stoicism 101\n2025·Erick Cloward",
	"The Republic\n1995·Plato",
	"Meditations\n1755·Marcus Aurelius",
	"Beyond Good and Evil\n1886·Friedrich Nietzsche",
	"Being and Nothingness\n1943·Jean-Paul Sartre"
]

var psychology_books = [
	"Thinking, Fast and Slow\n2011·Daniel Kahneman",
	"Man's Search for Meaning\n1946·Viktor Frankl",
	"The Body Keeps the Score\n2014·Bessel van der Kolk",
	"Man and His Symbols\n1964·Carl Jung",
	"Drive\n2009·Daniel H. Pink"
]

var education_books = [
	"Pedagogy of the Oppressed\n1968·Paulo Freire",
	"Educated\n2018·Tara Westover",
	"Teaching to Transgress\n1994·bell hooks",
	"Experience and Education\n1938·John Dewey",
	"Mindset\n2006·Carol S. Dweck"
]

var science_books = ["Astrophysics for People in a Hurry \nby Neil deGrasse Tyson", 
"A Short History of Nearly Everything\n by Bill Bryson", 
"Cosmos by Carl Sagan"]

var english_books = [
	"To Kill a Mockingbird\n1960·Harper Lee",
	"Pride and Prejudice\n1813·Jane Austen",
	"The Great Gatsby\n1925·F. Scott Fitzgerald",
	"1984\n1949·George Orwell",
	"The Catcher in the Rye\n1951·J.D. Salinger"
]

var law_books = [
	"The Republic\n380 BC·Plato",
	"The Common Law\n1881·Oliver Wendell Holmes Jr.",
	"On Liberty\n1859·John Stuart Mill",
	"The Rule of Law\n2010·Tom Bingham",
	"Introduction to the Study of the Law of the Constitution\n1885·A.V. Dicey"
]




# SHELVES (filled in _ready)
# to add a new shelf: add a books array above + ONE line in _ready

var shelves = {}


var currently_tracking = ""
var cam_tween: Tween

# CLICK LOCK
var is_camera_moving = false


# =================================
# CAMERA TWEEN
# =================================

func stop_camera_tween():
	if cam_tween and cam_tween.is_running():
		cam_tween.kill()


# =================================
# TRACKING
# =================================

func tracking():

	if currently_tracking != "":
		print("ARROW TO ", currently_tracking)
		arrow.visible = true
		sec_1_highlight.visible = true


# =================================
# READY (connects every shelf)
# =================================

func _ready():

	# name : [area, marker, books]
	shelves = {
		"PHILOSOPHY": [philosophy, philo_marker, philosophy_books],
		"PSYCHOLOGY": [psychology, psych_marker, psychology_books],
		"EDUCATION": [$Education, educ_marker, education_books],
		"SCIENCE": [science, sci_marker, science_books],
		"ENGLISH": [english, eng_marker, english_books],
		"LAW": [law, law_marker, law_books]
		}

	for shelf_name in shelves:

		var area: Area3D = shelves[shelf_name][0]

		# remove old editor connections so nothing fires twice
		for sig in [area.input_event, area.mouse_entered, area.mouse_exited]:
			for c in sig.get_connections():
				sig.disconnect(c["callable"])

		area.input_event.connect(_on_shelf_input_event.bind(shelf_name))
		area.mouse_entered.connect(_on_shelf_hover.bind(shelf_name, true))
		area.mouse_exited.connect(_on_shelf_hover.bind(shelf_name, false))


# =================================
# PROCESS
# =================================

func _process(delta: float) -> void:

	if Input.is_action_pressed("left"):
		print("left")
		camera_3d.rotation_degrees.y -= 1
		section_1_cam.rotation_degrees.y -= 1
		top_down_camera.rotation_degrees.y -= 1

	if Input.is_action_pressed("right"):
		print("right")
		camera_3d.rotation_degrees.y += 1
		section_1_cam.rotation_degrees.y += 1
		top_down_camera.rotation_degrees.y += 1


	# TOP DOWN CAMERA

	if Input.is_action_just_pressed("top_down") and not is_camera_moving:

		is_camera_moving = true
		stop_camera_tween()

		var target_pos = top_down_camera.global_position

		top_down_camera.global_position = camera_3d.global_position
		top_down_camera.make_current()

		cam_tween = create_tween()
		cam_tween.set_trans(Tween.TRANS_CUBIC)
		cam_tween.set_ease(Tween.EASE_IN_OUT)

		cam_tween.tween_property(
			top_down_camera,
			"global_position",
			target_pos,
			1.0
		)

		cam_tween.finished.connect(func():
			is_camera_moving = false
		)


	# BOTTOM UP CAMERA

	if Input.is_action_just_pressed("bottom_up") and not is_camera_moving:

		is_camera_moving = true
		stop_camera_tween()

		var target_position = camera_3d.global_position

		camera_3d.global_position = top_down_camera.global_position
		camera_3d.make_current()

		cam_tween = create_tween()
		cam_tween.set_trans(Tween.TRANS_CUBIC)
		cam_tween.set_ease(Tween.EASE_IN_OUT)

		cam_tween.tween_property(
			camera_3d,
			"global_position",
			target_position,
			1.0
		)

		cam_tween.finished.connect(func():
			is_camera_moving = false
		)


	# OVERVIEW CAMERA (key 4)

	if Input.is_action_just_pressed("4") and not is_camera_moving:

		is_camera_moving = true
		stop_camera_tween()

		var overview_pos = camera_3d.global_position

		camera_3d.global_position = section_1_cam.global_position
		camera_3d.make_current()

		cam_tween = create_tween()
		cam_tween.set_trans(Tween.TRANS_CUBIC)
		cam_tween.set_ease(Tween.EASE_IN_OUT)

		cam_tween.tween_property(
			camera_3d,
			"global_position",
			overview_pos,
			1.0
		)

		cam_tween.finished.connect(func():
			is_camera_moving = false
		)

		currently_tracking = ""
		arrow.visible = false
		sec_1_highlight.visible = false

		genre.text = ""
		label.text = ""

		genre_selected.visible = false
		genre_bg.visible = true


# =================================
# SHELVES (one set of code for all)
# =================================

func _on_shelf_input_event(camera: Node, event: InputEvent, event_position: Vector3, normal: Vector3, shape_idx: int, shelf_name: String):

	if Input.is_action_just_pressed("select") and not is_camera_moving:
		select_shelf(shelf_name)


func select_shelf(shelf_name: String):

	# only pan if we're not already on the section camera
	if not section_1_cam.current:

		is_camera_moving = true
		stop_camera_tween()

		var target_position = section_1_cam.global_position

		section_1_cam.global_position = camera_3d.global_position
		section_1_cam.make_current()

		cam_tween = create_tween()
		cam_tween.set_trans(Tween.TRANS_CUBIC)
		cam_tween.set_ease(Tween.EASE_IN_OUT)

		cam_tween.tween_property(
			section_1_cam,
			"global_position",
			target_position,
			1.0
		)

		cam_tween.finished.connect(func():
			is_camera_moving = false
		)

	genre.text = shelf_name
	label.text = "\n\n".join(shelves[shelf_name][2])

	currently_tracking = shelf_name

	genre_selected.visible = true
	genre_bg.visible = false


# =================================
# MOUSE HOVER MARKERS
# =================================

func _on_shelf_hover(shelf_name: String, is_hovering: bool) -> void:
	shelves[shelf_name][1].visible = is_hovering


# =================================
# TOP DOWN BUTTON
# =================================

func _on_button_pressed():

	if is_camera_moving:
		return

	is_camera_moving = true
	stop_camera_tween()

	var target_pos = top_down_camera.global_position

	top_down_camera.global_position = camera_3d.global_position
	top_down_camera.make_current()

	cam_tween = create_tween()
	cam_tween.set_trans(Tween.TRANS_CUBIC)
	cam_tween.set_ease(Tween.EASE_IN_OUT)

	cam_tween.tween_property(
		top_down_camera,
		"global_position",
		target_pos,
		1.0
	)

	cam_tween.finished.connect(tracking)

	cam_tween.finished.connect(func():
		is_camera_moving = false
	)


# =================================
# HIDE BUTTON
# =================================

func _on_button_2_pressed() -> void:

	hide.visible = false
	label.visible = false
	sprite_2d.visible = false

	show.visible = true


# =================================
# SHOW BUTTON
# =================================

func _on_hide_2_pressed() -> void:

	hide.visible = true
	label.visible = true
	sprite_2d.visible = true

	show.visible = false
