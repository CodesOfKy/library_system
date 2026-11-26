extends Node3D

@onready var default_camera: Camera3D = $default_camera
@onready var top_down_camera: Camera3D = $top_down_camera
@onready var section_1_cam: Camera3D = $section1_cam
@onready var section_2_cam: Camera3D = $section2_cam

@onready var genre: Label = $default_camera/genre
@onready var label: Label = $default_camera/Label
@onready var genre_selected: Sprite2D = $default_camera/genre_selected
@onready var genre_bg: Sprite2D = $default_camera/genre_bg

# SECTION 1 SHELVES
@onready var philo_marker: Sprite3D = $Philosophy/philo_marker
@onready var psych_marker: Sprite3D = $Psychology/psych_marker
@onready var educ_marker: Sprite3D = $Education/educ_marker
@onready var sci_marker: Sprite3D = $Science/sci_marker
@onready var eng_marker: Sprite3D = $English/eng_marker
@onready var law_marker: Sprite3D = $Law/law_marker

# SECTION 2
@onready var mis_marker: Sprite3D = $Miscellaneous/mis_marker
@onready var sec_2_highlight: Sprite3D = $sec2_highlight

@onready var sec_1_highlight: Sprite3D = $sec1_highlight
@onready var path_to_section_1: Path3D = $Path_To_Section_1


var currently_clicked := ""
var camera_tween: Tween
var current_cam: Camera3D
var is_camera_moving := false

var philosophy_books := ["book1", "book2", "book3"]
var psychology_books := ["book1", "book2", "book3"]
var education_books := ["book1", "book2", "book3"]
var science_books := ["book1", "book2", "book3"]
var english_books := ["book1", "book2", "book3"]
var law_books := ["book1", "book2", "book3"]
var misc_books := ["book1", "book2", "book3"]


func _ready():
	current_cam = default_camera
	default_camera.make_current()


func _process(delta):
	if Input.is_action_pressed("left"):
		default_camera.rotation_degrees.y += 1
		top_down_camera.rotation_degrees.y += 1
		section_1_cam.rotation_degrees.y += 1
		section_2_cam.rotation_degrees.y += 1

	if Input.is_action_pressed("right"):
		default_camera.rotation_degrees.y -= 1
		top_down_camera.rotation_degrees.y -= 1
		section_1_cam.rotation_degrees.y -= 1
		section_2_cam.rotation_degrees.y -= 1

	if Input.is_action_just_pressed("top_down"):
		animate_camera(top_down_camera)

	if Input.is_action_just_pressed("bottom_up"):
		animate_camera(default_camera)

	# key 4 = back to overview
	if Input.is_action_just_pressed("4"):
		animate_camera(default_camera)
		sec_1_highlight.visible = false
		sec_2_highlight.visible = false
		path_to_section_1.visible = false


func show_genre():
	if currently_clicked == "PHILOSOPHY":
		genre.text = "PHILOSOPHY"
		label.text = "\n \n".join(philosophy_books)

	if currently_clicked == "PSYCHOLOGY":
		genre.text = "PSYCHOLOGY"
		label.text = "\n \n".join(psychology_books)

	if currently_clicked == "EDUCATION":
		genre.text = "EDUCATION"
		label.text = "\n \n".join(education_books)

	if currently_clicked == "SCIENCE":
		genre.text = "SCIENCE"
		label.text = "\n \n".join(science_books)

	if currently_clicked == "ENGLISH":
		genre.text = "ENGLISH"
		label.text = "\n \n".join(english_books)

	if currently_clicked == "LAW":
		genre.text = "LAW"
		label.text = "\n \n".join(law_books)

	if currently_clicked == "MISC":
		genre.text = "MISC"
		label.text = "\n \n".join(misc_books)

	genre_selected.visible = true
	genre_bg.visible = false


func animate_camera(next_cam: Camera3D):
	# DEBUG: delete this line when everything works
	print("go to: ", next_cam.name, " | current: ", current_cam.name, " | moving: ", is_camera_moving)

	if next_cam == current_cam or is_camera_moving:
		return

	is_camera_moving = true

	var target_pos = next_cam.global_position
	next_cam.global_position = current_cam.global_position
	next_cam.make_current()
	current_cam = next_cam

	camera_tween = create_tween()
	camera_tween.set_trans(Tween.TRANS_CUBIC).set_ease(Tween.EASE_IN_OUT)
	camera_tween.tween_property(next_cam, "global_position", target_pos, 1.0)
	camera_tween.finished.connect(func(): is_camera_moving = false)


# CLICKS
func _on_philosophy_input_event(camera: Node, event: InputEvent, event_position: Vector3, normal: Vector3, shape_idx: int):
	if Input.is_action_just_pressed("select"):
		currently_clicked = "PHILOSOPHY"
		animate_camera(section_1_cam)
		show_genre()

func _on_psychology_input_event(camera: Node, event: InputEvent, event_position: Vector3, normal: Vector3, shape_idx: int):
	if Input.is_action_just_pressed("select"):
		currently_clicked = "PSYCHOLOGY"
		animate_camera(section_1_cam)
		show_genre()

func _on_education_input_event(camera: Node, event: InputEvent, event_position: Vector3, normal: Vector3, shape_idx: int):
	if Input.is_action_just_pressed("select"):
		currently_clicked = "EDUCATION"
		animate_camera(section_1_cam)
		show_genre()

func _on_science_input_event(camera: Node, event: InputEvent, event_position: Vector3, normal: Vector3, shape_idx: int):
	if Input.is_action_just_pressed("select"):
		currently_clicked = "SCIENCE"
		animate_camera(section_1_cam)
		show_genre()

func _on_english_input_event(camera: Node, event: InputEvent, event_position: Vector3, normal: Vector3, shape_idx: int):
	if Input.is_action_just_pressed("select"):
		currently_clicked = "ENGLISH"
		animate_camera(section_1_cam)
		show_genre()

func _on_law_input_event(camera: Node, event: InputEvent, event_position: Vector3, normal: Vector3, shape_idx: int):
	if Input.is_action_just_pressed("select"):
		currently_clicked = "LAW"
		animate_camera(section_1_cam)
		show_genre()

func _on_miscellaneous_input_event(camera: Node, event: InputEvent, event_position: Vector3, normal: Vector3, shape_idx: int):
	if Input.is_action_just_pressed("select"):
		currently_clicked = "MISC"
		animate_camera(section_2_cam)
		show_genre()


# HOVER MARKERS
func _on_philosophy_mouse_entered() -> void:
	philo_marker.visible = true
func _on_philosophy_mouse_exited() -> void:
	philo_marker.visible = false

func _on_psychology_mouse_entered() -> void:
	psych_marker.visible = true
func _on_psychology_mouse_exited() -> void:
	psych_marker.visible = false

func _on_education_mouse_entered() -> void:
	educ_marker.visible = true
func _on_education_mouse_exited() -> void:
	educ_marker.visible = false

func _on_science_mouse_entered() -> void:
	sci_marker.visible = true
func _on_science_mouse_exited() -> void:
	sci_marker.visible = false

func _on_english_mouse_entered() -> void:
	eng_marker.visible = true
func _on_english_mouse_exited() -> void:
	eng_marker.visible = false

func _on_law_mouse_entered() -> void:
	law_marker.visible = true
func _on_law_mouse_exited() -> void:
	law_marker.visible = false

func _on_miscellaneous_mouse_entered() -> void:
	mis_marker.visible = true
func _on_miscellaneous_mouse_exited() -> void:
	mis_marker.visible = false


# TRACKING BUTTON
func _on_button_pressed():
	if currently_clicked == "":
		path_to_section_1.visible = false
		return
	animate_camera(top_down_camera)
	
	if currently_clicked == "MISC":
		
		sec_1_highlight.visible = false
		sec_2_highlight.visible = true
	else:
		path_to_section_1.visible = true
		sec_1_highlight.visible = true
		sec_2_highlight.visible = false
		
